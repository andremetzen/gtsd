var util = require('util');
var os = require('os');
var net = require('net');
var http = require('http');
    
// Função responsável por criar identificadores únicos
function s4() {
  return Math.floor((1 + Math.random()) * 0x10000)
             .toString(16)
             .substring(1);
};

function generateId(t) {
  return t+'-'+s4();// + s4() + '-' + s4() + '-' + s4() + '-' +
         //s4() + '-' + s4() + s4() + s4();
}

// Constantes para identificação das mensagens
var MESSAGE_SEPARATOR = "\n\n";
var MESSAGE_ACKNOWLEDGE = 1;
var MESSAGE_CONNECTION_ESTABLISHED = 2;
var MESSAGE_FUNCTION_REGISTER = 3;
var MESSAGE_WORK_REQUEST = 4;
var MESSAGE_NO_WORK = 5;
var MESSAGE_WAKE = 6;
var MESSAGE_RUN_WORK = 7;
var MESSAGE_WORK_CREATED = 8;
var MESSAGE_WORK_RUNNING = 9;
var MESSAGE_WORK_STATUS = 10;
var MESSAGE_WORK_DONE = 11;

// Classe que representa uma mensagem trocada entre um agente e outro
var Message = function(code, body){
    this.header = {
        code: code,
        origin: os.hostname()+":"+process.argv[2],
        id: generateId('m')
    };
    
    this.body = (body == undefined) ? {} : body;
    
    this.parse = function(data){
        json = JSON.parse(data);
        for(x in json)
            this[x] = json[x];
        
        return this;
    };
    
    this.getCode = function(){
        return this.header.code;
    };
};

// Classe que representa uma conexão com um agente qualquer
var Connection = function(socket, agent){
    this.socket = socket;
    this.socket.agent = agent;
    var active = true;
    
    this.write = function(message){
        if(active)
        {
            //console.log(">> "+JSON.stringify(message));
            this.socket.write(JSON.stringify(message)+MESSAGE_SEPARATOR);
        }
    };

    this.close = function(){
        active = false;
    }
};

// Classe que representa o Client
var Client = function(socket, hostname){
    this.id = generateId('c');
    this.conn = new Connection(socket, this);
    this.hostname = hostname;
    
    this.onConnect = function(){
        this.conn.write(new Message(
            MESSAGE_CONNECTION_ESTABLISHED,
            {connection_id: this.id})
        );
    };
    
    this.onCreateJob = function(job){
        this.conn.write(new Message(MESSAGE_WORK_CREATED, {jobid: job.id}));
    };
    
    this.onWorkProgress = function(job){
        this.conn.write(new Message(MESSAGE_WORK_STATUS, job.getBodyMessage()));
    };
    
    this.onWorkDone = function(job){
        gtsd.logs.push('Gerente: cliente '+this.id+', pega o resultado do job '+job.id);
        this.conn.write(new Message(MESSAGE_WORK_DONE, job.getBodyMessage()));
    };
    
    this.onClose = function(){
        this.conn.close();
        //console.log("Client connection ["+this.conn.socket.remoteAddress+"] closed");
    };
    
    this.onConnect();
};

// Classe que representa um Worker
var Worker = function(socket, hostname){
    this.id = generateId('w');
    this.conn = new Connection(socket, this);
    this.hostname = hostname;
    this.workingAt = null;
    this.functions = [];
    this.state = 'sleep';
    
    this.onConnect = function(){
        var body = {connection_id: this.id},
            msg = new Message(MESSAGE_CONNECTION_ESTABLISHED, body);
        this.conn.write(msg);
    };
    
    this.onClose = function(){
        var conn = this.conn;
        conn.close();
        util.log("Worker connection ["+conn.socket.remoteAddress+"] closed");
    };
    
    this.wake = function(){
        this.conn.write(new Message(MESSAGE_WAKE, {}));
        this.state = 'wake';
    };
    
    this.canDo = function(function_name){
        for(var i = 0, l = this.functions.length; i < l; i++)
        {
            if(this.functions[i] == function_name) {
                return true;
            }
        }
        
        return false;
    };
    
    this.runWork = function(work){
        this.workingAt = work;
        work.worker = this;
        this.conn.write(new Message(MESSAGE_RUN_WORK, work.getBodyMessage()));
        this.state = 'working';
    };
    
    this.onWorkDone = function(work){
        this.workingAt = null;
        this.state = 'sleep';
    };
    
    this.noWork = function(){
        this.conn.write(new Message(MESSAGE_NO_WORK, {}));
        this.state = 'sleep';
    };
    
    this.onConnect();
};

// Classe que representa um trabalho a ser executado ou em execução
var Job = function(client, function_name, workload, priority, async){
    this.id = generateId('j');
    this.function_name = function_name;
    this.workload = workload;
    this.status = 'created';
    this.progress = 0;
    this.owner = client;
    this.worker = null;
    this.result = null;
    this.priority = priority;
    this.async = async;
    
    this.getBodyMessage = function()
    {
        return {
            jobid: this.id,
            function_name: this.function_name,
            workload: this.workload,
            status: this.status,
            progress: this.progress,
            result: this.result
        };
    };
    
    // Notifica o cliente quando há alguma alteração no progresso da execução do
    // job
    this.onProgress = function(message){
        this.status = message.body.status;
        this.progress = message.body.progress;
        this.owner.onWorkProgress(this);
    };
    
    // Encaminha o resultado do job para o cliente e notifica libera o worker
    this.onFinish = function(message){
        this.result = message.body.result;
        this.worker.onWorkDone(this);
        this.owner.onWorkDone(this);
    };
    
    // Notifica ao cliente que o job foi criado com sucesso
    this.onCreate = function(){
        this.owner.onCreateJob(this);
    };
    
    this.onCreate();
};

// Classe representa o servidor, responsavel por escutar conexões e 
// orquestrar o funcionamento de todos os outros componentes
var Server = function(){
    var server = this;
    this.workers = [];
    this.clients = [];
    this.jobs = [];
    this.logs = [];
    this.name;
    
    // Instancia um socket e adiciona os manipuladores de eventos 
    this.conn = net.createServer(function (socket) {
        socket.buffer = "";
            
        socket.setEncoding("utf8");
        
        // Adiciona um manipulador no evento de conexão
        socket.addListener("connect", function () {
            console.log("["+socket.remoteAddress+"] open connection");
        });
        
        // Adiciona um manipulador no evento de recebimento de dados
        socket.addListener("data", function (data) {
            // Divide os dados por quebra de linha e interpreta cada mensagem
            // individualmente
            socket.buffer += data;
            //console.log("<< "+data);
            if(socket.buffer.search(MESSAGE_SEPARATOR) !== -1)
            {
                var messages = socket.buffer.split(MESSAGE_SEPARATOR);
                for(var i = 0; i<messages.length-1; i++)
                {
                    if(messages[i].length > 0)
                        parseMessage(this, messages[i]);

                    delete messages[i];
                }

                socket.buffer = messages.join(MESSAGE_SEPARATOR);
            }
        });
        
        // Adiciona um manipulador no evento de encerramento de conexão
        socket.addListener("end", function () {
            disconnect(this.agent);
            
        });
    });
    
    // Manipulador responsavel por encerrar uma conexão
    var disconnect = function(agent){
        // Fecha o socket
        agent.onClose();
        
        // Remove da lista de clients
        for(var i in this.clients)
        {
            if(this.clients[i] == agent)
            {
                 this.clients.slice(0,i).concat( this.clients.slice(i+1) );
                 return true;
            }   
        }
        
        // Remove da lista de workers
        for(var i in this.workers)
        {
            if(this.workers[i] == agent)
            {
                 this.workers.slice(0,i).concat( this.workers.slice(i+1) );
                 return true;
            }   
        }
    };
    
    // Inicializa o socket escutando na porta 'port' e IP 'host' 
    this.create = function(port, host, monitor, name){
        this.conn.listen(port, host);
        this.name = name;
        monitor = new Monitor(this, monitor);
        
    }
    
    // Meétodo responsável por instanciar o objeto Message e interpretá-lo
    // de acordo com o código
    var parseMessage = function(socket, data){
        try{
            message = new Message().parse(data);
            
            switch(message.getCode())
            {
                case MESSAGE_ACKNOWLEDGE:
                    acknowledge(socket, message);
                    break;

                case MESSAGE_RUN_WORK:
                    createWork(socket, message);
                    break;

                case MESSAGE_FUNCTION_REGISTER:
                    registerFunctions(socket, message);
                    break;

                case MESSAGE_WORK_REQUEST:
                    workRequest(socket, message);
                    break;

                case MESSAGE_WORK_STATUS:
                    workStatus(socket, message);
                    break;

                case MESSAGE_WORK_DONE:
                    workDone(socket, message);
                    break;
            }
        }
        catch(e)
        {
            console.log(e.stack);
            util.log("["+socket.remoteAddress+"] unable to parse message");
            return false;
        }
    };
    
    // Trata a mensagem de que houve uma mudança no progresso do job
    // e atualiza sua informação notificando ao client
    var workStatus = function(socket, message){
        server.logs.push('Worker ('+socket.agent.id+'): O trabalho '+message.body.jobid+' progrediu');
        socket.agent.workingAt.onProgress(message);
    };
    
    // Trata a mensagem de que um trabalho foi concluído e informa ao client
    // Informa ao agente que um trabalho foi concluído
    var workDone = function(socket, message){
        server.logs.push('Worker ('+socket.agent.id+'): Terminei o trabalho '+message.body.jobid);
        if(socket.agent.workingAt)
            socket.agent.workingAt.onFinish(message);                
    };
    
    // Trata a mensagem de solicitação de trabalho pelo worker. Ele recebe um 
    // job se for capaz de executar algum que esteja na fila de espera
    var workRequest = function(socket, message){
        server.logs.push('Worker ('+socket.agent.id+'): Ei, tem trabalho pra mim?');
        if(socket.agent.workingAt != null)
            return;

        for(var i in server.jobs)
        {
            if(socket.agent.canDo(server.jobs[i].function_name))
            {
                var job = server.jobs[i];
                server.jobs = server.jobs.slice(0,i).concat( server.jobs.slice(i+1) );
                server.logs.push('Gerente: Tem sim, pega ai o job '+job.id);
                return socket.agent.runWork(job);
            }
        }
        
        server.logs.push('Gerente: Tem nao');
                
        socket.agent.noWork();
    };
    
    // Trata a mensagem enviada pelo client para adicionar um novo job na fila.
    // Em seguida acorda os workers para ver se algum é capaz de executar o job
    var createWork = function(socket, message){
        var job = new Job(  socket.agent,
                            message.body.function_name,
                            message.body.workload,
                            message.body.priority,
                            message.body.async
                        );
        //util.log("["+socket.remoteAddress+"] job requested");                    
        server.logs.push('Cliente ('+socket.agent.id+'): Faz o trabalho '+job.id+' pra mim ai');
        server.jobs.push(job);        
        wakeWorkers();
    };
    
    // Trata a mensagem enviada pelo worker informando quais funções ele é capaz
    // de executar
    var registerFunctions = function(socket, message){
        socket.agent.functions = message.body.functions;
    };
    
    // Acorda todos os workers
    var wakeWorkers = function(){
        for(var i in server.workers)
        {
            if(server.workers[i].workingAt == null)
            {
                
                server.logs.push('Gerente: Acorda ai '+server.workers[i].id);
                server.workers[i].wake();
            }
        }
    };    
    
    // Trata a mensagem de acknowledge e adiciona o agente a sua respectiva
    // lista de agentes
    var acknowledge = function(socket, message){
        if(message.body.type == "client")
        {
            var client = new Client(socket, message.header.origin);
            server.clients.push(client);
        }
        else if(message.body.type == "worker")
        {
            var worker = new Worker(socket, message.header.origin);
            server.workers.push(worker);
        }
    };
};

var Monitor = function(server, port){
    var that = this;
    this.server = server;
    
    http.createServer(function (req, res) {
      res.writeHead(200, {'Content-Type': 'application/json'});
      
      var jobs = [];
      for(var i in that.server.jobs)
      {
          jobs.push(that.server.jobs[i].getBodyMessage())
      }
      
      var workers = [];
      for(var i in that.server.workers)
      {
          var w = that.server.workers[i];
          workers.push({id: w.id, hostname: w.hostname, workingAt: ((w.workingAt !== null) ? true: false), functions: w.functions, state: w.state});
      }
      
      
      
      res.end(JSON.stringify({jobs: jobs,  workers: workers, log: that.server.logs}));
    }).listen(port, "127.0.0.1");
};

// Inicia o servidor com as configurações passadas pela linha de comando
// ex: node server.js 8124 localhost
gtsd = new Server;
gtsd.create(process.argv[2], process.argv[3], process.argv[4] || 8080, process.argv[5] || 'server');