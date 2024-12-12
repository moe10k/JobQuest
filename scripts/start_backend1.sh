#!/bin/bash
for file in ../backend1/*.php; do
   gnome-terminal -- bash -c "php $file; exec bash"
done

echo "all php scripts started"