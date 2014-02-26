#!/bin/sh

echo "Test #9 - reverse 1000 with length of 1000 strings without Gtsd"
echo "Running..."
/usr/bin/time -f "Elapsed time: %E" ./reverse_without_gtsd.php strings_1000.txt > /dev/null