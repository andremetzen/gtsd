import serverpool

class Agent:
	
	def __init__(self, servers):
		self.serverpool = serverpool.create(servers, self)
