"""Kleiner HTTP-Dienst: POST /embed {"texte": [...], "art": "frage"|"dokument"} -> {"modell", "dim", "vektoren"} (normiert).

Nur im Docker-Netz und auf 127.0.0.1 erreichbar (compose.yml). Das Modell wird beim ersten Start nach /cache geladen."""
import json
import os
import threading
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

import numpy as np
from fastembed import TextEmbedding

MODEL = os.environ.get('EMBED_MODEL', 'jinaai/jina-embeddings-v2-base-de')
model = TextEmbedding(MODEL, threads=int(os.environ.get('EMBED_THREADS', '2')))
lock = threading.Lock()  # ein Rechenauftrag zur Zeit, sonst wächst der Speicherbedarf


class Handler(BaseHTTPRequestHandler):
    def do_GET(self):
        self.reply(200, {'modell': MODEL})

    def do_POST(self):
        if self.path != '/embed':
            return self.reply(404, {'fehler': 'unbekannter Pfad'})
        try:
            req = json.loads(self.rfile.read(int(self.headers.get('Content-Length', 0))))
            texte = [str(t) for t in req['texte']][:64]
        except (ValueError, KeyError, TypeError):
            return self.reply(400, {'fehler': 'erwartet {"texte": [...]}'})
        with lock:
            gen = model.query_embed(texte) if req.get('art') == 'frage' else model.passage_embed(texte, batch_size=4)
            v = np.array(list(gen), dtype=np.float32)
        v /= np.maximum(np.linalg.norm(v, axis=1, keepdims=True), 1e-9)
        self.reply(200, {'modell': MODEL, 'dim': int(v.shape[1]), 'vektoren': v.round(6).tolist()})

    def reply(self, status, data):
        body = json.dumps(data).encode()
        self.send_response(status)
        self.send_header('Content-Type', 'application/json')
        self.send_header('Content-Length', str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def log_message(self, *args):
        pass  # keine Fragen ins Log


ThreadingHTTPServer(('0.0.0.0', 8000), Handler).serve_forever()
