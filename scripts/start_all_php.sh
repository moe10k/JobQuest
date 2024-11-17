#!/bin/bash
for file in ../backend1/*.php; do
   gnome-terminal -- bash -c "php $file; exec bash"
done


for file in ../database/*.php; do
   gnome-terminal -- bash -c "php $file; exec bash"
done


for file in ../backend2/*.php; do
   gnome-terminal -- bash -c "php $file; exec bash"
done


echo "all php scripts started"