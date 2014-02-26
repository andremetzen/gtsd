#!/bin/sh

echo "Test UI - resize an image by part"

echo "Starting 2 servers..."
node ../server/server.js 8124 localhost 8080 server1 & pid_server1=$!
node ../server/server.js 8125 localhost 8081 server2 & pid_server2=$!
sleep 1

echo "Starting workers..."
./ui_worker.php localhost:8124,localhost:8125 worker1 & pid_worker1=$!
./ui_worker.php localhost:8124,localhost:8125 worker2 & pid_worker2=$!

echo "Running..."
./ui_test.py localhost:8124,localhost:8125 & pid_ui=$!

wait $pid_ui
kill $pid_worker1
kill $pid_worker2
kill $pid_server1
kill $pid_server2