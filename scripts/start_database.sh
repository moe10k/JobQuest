#!/bin/bash
for file in ../database/*.php; do
   gnome-terminal -- bash -c "php $file; exec bash"
done

echo "all php scripts started"