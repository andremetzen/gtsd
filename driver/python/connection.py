import socket
from message import *
from pprint import pprint
import select

class Connection:  

	def __init__(self, server, port, agent):
		self.agent = agent
		self.socket = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
		self.socket.connect((server, port))
		self.id = None
		self.buffer = []
                self.blocking(0)
		self.acknowledge()

	def acknowledge(self):
		self.write(Message(Message.ACKNOWLEDGE, {'type': self.agent.type}))
		m = self.read()
		
		if isinstance(m, Message) and m.getCode() == Message.CONNECTION_ESTABLISHED:
			self.id = m.body['connection_id']
			return True
		return False

	def blocking(self, flag):
		self.socket.setblocking(flag)

	def read(self):
		inputready,outputready,exceptready = select.select([self.socket], [], [], 1)
		if len(inputready) == 0:
			return None

		buffer = ""
		while buffer.find("\n") == -1:
			buffer += self.socket.recv(1024)
		
		self.buffer.extend(buffer.strip("\n").split("\n"))
		
		buffer = self.buffer.pop(0)
		if len(buffer) > 0:
			#print "Received: "+buffer
			m = Message.fromJson(buffer)
			m.header['connection_id'] = self.id
		else:
			return None
		
		return m
		
	def write(self, message):
		#print "Sent: "+message.toJson()
		self.socket.send(message.toJson()+"\n")




