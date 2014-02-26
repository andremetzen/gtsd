from serverpool import *
from agent import *
from message import *
from task import *
from pprint import pprint

class Worker(Agent):
	type = 'worker'
	
	def __init__(self, servers):
		Agent.__init__(self, servers)
		self.functions = {}
		self.registered = False
		self.serverpool.connectAll()
		
	def __registerFunctions(self):
		if not self.registered:
			self.serverpool.writeAll(Message(Message.FUNCTION_REGISTER, {'functions': self.functions.keys()}))
			self.registered = True
			
	def __requestWork(self, wakeMsg=None):
		msg = Message(Message.WORK_REQUEST)
		print "requesting work"
		if not isinstance(wakeMsg, Message):
			self.serverpool.writeAll(msg)
		else:
			self.serverpool.write(msg, wakeMsg.header['connection_id'])
			
		
	def __workComplete(self, task):
		#pprint((vars(task)))
		self.serverpool.write(Message(Message.WORK_DONE, {'jobid': task.id, 'result': task.result}), task.connection_id)
		
	def register(self, function, callback):
		self.functions[function] = callback
		
	def work(self):
		self.__registerFunctions()
		self.__requestWork()
		message = self.serverpool.readAll()
		
		while self.__executeMessage(message):
			message = self.serverpool.readAll()
			print "..."
		
		return True
		
	def __executeMessage(self, message):
		if not isinstance(message, Message):
			return True
			
		if message.getCode() == Message.NO_WORK:
			print "no work"
			return True
		elif message.getCode() == Message.WAKE:
			print "wake"
			self.__requestWork()
			return True
		elif message.getCode() == Message.RUN_WORK:
			print "run work"
			task = self.__run(message)
			self.__workComplete(task)
			return False
		
		return True
		
	def __run(self, m):
		task = Task(self, m.body['function_name'], m.body['workload'])
		task.updateInfo(m)
		task.connection_id = m.header['connection_id']
		task.status = 'working'
		callback = self.functions[task.function_name]
		
		if hasattr(callback, '__call__'):
			task.result = callback(task)
			
		return task
		
	
			
	
