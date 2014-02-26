from agent import *
from message import *
from task import *

class Client(Agent):
	type = 'client'

	
	def __init__(self, servers):
		Agent.__init__(self, servers)
		self.callback = {}
		
	def run(self, function, workload, options={}):
		default = {'priority': 0, 'async': False}
		options = dict(default.items() + options.items())
		
		m = Message(Message.RUN_WORK, {'function_name': function, 'workload': workload, 'priority': options['priority'], 'async': options['async']});
		self.serverpool.suffleConnection()
		self.serverpool.write(m)
		m = self.serverpool.read()
		self.task = Task(self, function, workload)
		self.runCallback('create', m)
		
		if not options['async']:
			response = self.serverpool.read()
			while not isinstance(response, Message):
				response = self.serverpool.read()
			
			self.task.updateInfo(response)
			
			if response.getCode() == Message.WORK_DONE:
				task = self.task
				self.runCallback('finish', response)
				self.task = None
				return task.result
			elif response.getCode() == Message.WORK_STATUS:
				self.runCallback('status', response)	
		
		return None
		
	def runCallback(self, type, message):
		if type in self.callback and hasattr(self.callback[type], '__call__'):
			self.task.updateInfo(message)
			self.callback[type](self.task)
		return None
		
	def onStatus(self, callback):
		self.callback['status'] = callback
	
	def onCreate(self, callback):
		self.callback['create'] = callback
		
	def onFinish(self, callback):
		self.callback['finish'] = callback
		
	
