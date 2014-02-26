#!/bin/sh

echo "Test #14 - resize 8 images with Gtsd (1 server, 1 worker)"

echo "Starting server..."
node ../server/server.js 8124 localhost > /dev/null & pid_server=$!
sleep 1

echo "Starting worker..."
./worker.php localhost:8124 > /dev/null & pid_worker=$!

echo "Running..."
/usr/bin/time -f "Elapsed time: %E" ./resize_with_gtsd.php localhost:8124 > /dev/null 

kill $pid_worker
kill $pid_server