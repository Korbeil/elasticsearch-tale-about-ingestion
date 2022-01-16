#!/bin/bash

DATASET=${1:-20220101}
YEAR=${DATASET:0:4}

wget http://discogs-data.s3-us-west-2.amazonaws.com/data/${YEAR}/discogs_${DATASET}_artists.xml.gz
gunzip discogs_${DATASET}_artists.xml.gz
mv discogs_${DATASET}_artists.xml discogs_artists.xml
