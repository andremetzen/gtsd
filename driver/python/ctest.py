from client import *
from message import *

servers = [{'host': 'localhost', 'port': 8124}]

c = Client(servers)

for i in range(1000):
	print c.run("reverse", "minha mensagem"+str(i))


