import json, socket

class MessageEncoder(json.JSONEncoder):
	def default(self, m):
		if isinstance(m, Message):
			return {'header': m.header, 'body': m.body}
		return json.JSONEncoder.default(self, obj)

class Message:
	ACKNOWLEDGE = 1
	CONNECTION_ESTABLISHED = 2
	FUNCTION_REGISTER = 3
	WORK_REQUEST = 4
	NO_WORK = 5
	WAKE = 6
	RUN_WORK = 7
	WORK_CREATED = 8
	WORK_RUNNING = 9
	WORK_STATUS = 10
	WORK_DONE = 11

	def __init__(self, code, body={}):
		self.header = {'code': code, 'origin': socket.gethostname()}
		self.body = body;
	
	def getCode(self):
		return self.header['code']
	
	def toJson(self):
		return json.dumps(self, cls=MessageEncoder);
	
	@staticmethod	
	def fromJson(data):
		dct = json.loads(data);
		if 'body' not in dct:
			dct['body'] = {}
		if 'header' not in dct:
			dct['header'] = {}
			if 'code' not in dct['header']:
				dct['header']['code'] = 0;
		return Message(dct['header']['code'], dct['body'])

