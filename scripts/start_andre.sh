#!/bin/bash

ssh awh9@10.147.17.11 << EOF
cd scripts
chmod +x run_flask.sh
./run_flask.sh
EOF

