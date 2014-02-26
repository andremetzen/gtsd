from connection import *
from message import *
from random import choice
import select


def create(servers, agent):
	return ServerPool(servers, agent)

class ServerPool:
	
	def __init__(self, servers, agent):
		self.servers = servers;
		self.conns = []
		self.key = 0
		self.agent = agent
		
	def connect(self, server):
		server['conn'] = Connection(server['host'],server['port'], self.agent);
		server['key'] = len(self.conns)
		self.conns.append(server['conn'])
		
	def connectAll(self):
		[self.connect(i) for i in self.servers]
		self.last = choice(self.conns)
	
	def suffleConnection(self):
		server = choice(self.servers)
		if 'conn' in server and isinstance(server['conn'], Connection):
			self.setLastConn(server['key'])
		else:
			self.connect(server)
			self.setLastConn(server['key'])
	
	def setLastConn(self, key):
		self.last = self.conns[key]
	
	def getLastConn(self):
		return self.last
	
	def read(self):
		return self.getLastConn().read()
	
	def whoIsReady(self):
		sockets = []
                inputready = []
		for i in self.conns:
			sockets.append(i.socket)
		
                while len(inputready) <= 0:
                    inputready,outputready,exceptready = select.select(sockets, [], [], 1)
                    print inputready;

		return inputready[0];
	
        def getConnectionBySocket(self, socket):
                for j in self.conns:
                        if socket == j.socket:
                                return j

	def readAll(self):
		ready = self.whoIsReady()
		conn = self.getConnectionBySocket(ready)		
		return conn.read()
		
	def write(self, message, to=None):
		if to != None:
			conn = [x for x in self.conns if x.id == to][0]
		else:
			conn = self.getLastConn()	
		
		return conn.write(message)
		
	def writeAll(self, message):
		[x.write(message) for x in self.conns]	
		
	
