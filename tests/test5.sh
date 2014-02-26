#!/bin/sh

echo "Test #5 - reverse 1000 with length of 100 strings without Gtsd"
echo "Running..."
/usr/bin/time -f "Elapsed time: %E" ./reverse_without_gtsd.php strings_100.txt > /dev/null