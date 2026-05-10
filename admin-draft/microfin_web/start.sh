#!/bin/bash
PORT="${PORT:-8000}"
php -S 0.0.0.0:${PORT} -t .
