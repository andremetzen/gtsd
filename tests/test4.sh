#!/bin/sh

echo "Test #4 - reverse 1000 with length of 10 strings with Gtsd (2 server, 2 worker)"

echo "Starting servers..."
node ../server/server.js 8124 localhost 8080 > /dev/null & pid_server1=$!
node ../server/server.js 8125 localhost 8081 > /dev/null & pid_server2=$!
sleep 1

echo "Starting workers..."
./worker.php localhost:8124,localhost:8125 > /dev/null & pid_worker1=$!
./worker.php localhost:8124,localhost:8125 > /dev/null & pid_worker2=$!

echo "Running..."
/usr/bin/time -f "Elapsed time: %E" ./reverse_with_gtsd.php localhost:8124,localhost:8125 strings_10.txt > /dev/null 

kill $pid_worker1
kill $pid_worker2
kill $pid_server1
kill $pid_server2