#!/bin/bash

CURRENT_DIR=$(dirname $(readlink -f $0))

docker-compose -f $CURRENT_DIR/docker-compose.yaml exec clickhouse bash