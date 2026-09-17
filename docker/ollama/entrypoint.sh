#!/bin/sh
set -e

ollama serve &
SERVER_PID=$!

echo "Waiting for Ollama server..."
until ollama list >/dev/null 2>&1; do
    sleep 1
done

echo "Pulling model ${OLLAMA_MODEL:-llama3.2:3b}..."
ollama pull "${OLLAMA_MODEL:-llama3.2:3b}"

wait "$SERVER_PID"
