#!/bin/sh

echo "Test #11 - reverse 1000 with length of 1000 strings with Gtsd (1 server, 2 worker)"

echo "Starting server..."
node ../server/server.js 8124 localhost > /dev/null & pid_server=$!
sleep 1

echo "Starting workers..."
./worker.php localhost:8124 > /dev/null & pid_worker1=$!
./worker.php localhost:8124 > /dev/null & pid_worker2=$!

echo "Running..."
/usr/bin/time -f "Elapsed time: %E" ./reverse_with_gtsd.php localhost:8124 strings_1000.txt > /dev/null 

kill $pid_worker1
kill $pid_worker2
kill $pid_server