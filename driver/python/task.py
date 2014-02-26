from message import * 
from pprint import pprint

class Task:
	
	def __init__(self, agent, function_name, workload):
		self.agent = agent
		self.function_name = function_name
		self.workload = workload
		self.progress = 0
		self.result = None
		self.id = None
		self.status = 'created'
		self.connection_id = None
		
	def updateInfo(self, message):
		self.id = message.body['jobid']
		
		#pprint((vars(message)))
		if 'connection_id' in message.header:
			self.connection_id = message.header['connection_id']
		
		if message.getCode() == Message.WORK_STATUS:
			self.status = message.body['status']
			self.progress = message.body['progress']
		elif message.getCode() == Message.WORK_DONE:
			self.status = 'finished'
			self.result = message.body['result']
			self.progress = 100
		
		
	def setStatus(progress):
		self.progress = progress
		if self.agent.type == 'worker':
			self.agent.notifyWorkStatus(self)
