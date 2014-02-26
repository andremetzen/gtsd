#!/bin/sh

echo "Test #13 - resize 8 images without Gtsd"
echo "Running..."
/usr/bin/time -f "Elapsed time: %E" ./resize_without_gtsd.php > /dev/null