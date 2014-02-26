#!/bin/sh

echo "Test #1 - reverse 1000 with length of 10 strings without Gtsd"
echo "Running..."
/usr/bin/time -f "Elapsed time: %E" ./reverse_without_gtsd.php strings_10.txt > /dev/null