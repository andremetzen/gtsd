from worker import *
import time


servers = [{'host': 'localhost', 'port': 8124}, {'host': 'localhost', 'port': 8125}]

w = Worker(servers)

def reverse(task):
	return task.workload[::-1]
        

def upper(task):
	return task.workload.upper()

w.register("reverse", reverse)
w.register("upper", upper)

while w.work():
	print "Done!"

