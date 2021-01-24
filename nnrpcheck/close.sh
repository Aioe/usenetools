#!/bin/bash


pidban=$(cat pids/bannnrp)
pidcheck=$(cat pids/checknnrp)

kill $pidban
kill $pidcheck
