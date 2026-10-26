"""Kleiner HTTP-Dienst: POST /embed {"texte": [...], "art": "frage"|"dokument"} -> {"modell", "dim", "vektoren"} (normiert).
POST /suche {"frage": "..."} -> {"modell", "stand", "rang"}: Abschnitte des Stadtwissens (wissen/.abschnitte.json, ohne
„immer“-Seiten) nach Ähnlichkeit zur Frage, beste zuerst, als Positionen im Index; „stand“ = Änderungszeit der Datei,
damit der KI-Assistent prüfen kann, dass er denselben Index gelesen hat. Die Vektoren liegen dafür einmal im Speicher.

Nur im Docker-Netz und auf 127.0.0.1 erreichbar (compose.yml). Das Modell wird beim ersten Start nach /cache geladen."""
import base64
import json
import os
import threading
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

import numpy as np
from fastembed import TextEmbedding

MODEL = os.environ.get('EMBED_MODEL', 'jinaai/jina-embeddings-v2-base-de')
model = TextEmbedding(MODEL, threads=int(os.environ.get('EMBED_THREADS', '2')))
lock = threading.Lock()  # ein Rechenauftrag zur Zeit, sonst wächst der Speicherbedarf
DIM = len(next(iter(model.query_embed(['Uffenheim']))))

INDEX = os.environ.get('WISSEN_INDEX', '/srv/wissen/.abschnitte.json')
index_lock = threading.Lock()
index = {'stand': None, 'modell': None, 'ids': np.zeros(0, dtype=np.int64), 'matrix': np.zeros((0, DIM))}


def load_index():
    """Index neu lesen, wenn sich die Datei geändert hat (nächtlicher Abgleich ersetzt sie in einem Schritt)"""
    stand = int(os.stat(INDEX).st_mtime)
    with index_lock:
        if index['stand'] != stand:
            with open(INDEX, encoding='utf-8') as f:
                data = json.load(f)
            ids, vecs = [], []
            for i, c in enumerate(data.get('abschnitte') or []):
                if c.get('vec') and not c.get('immer'):
                    v = np.frombuffer(base64.b64decode(c['vec']), dtype='<f4')
                    if len(v) == DIM:
                        ids.append(i)
                        vecs.append(v)
            index.update(stand=stand, modell=data.get('modell'), ids=np.array(ids, dtype=np.int64),
                         matrix=np.vstack(vecs).astype(np.float64) if vecs else np.zeros((0, DIM)))
        return dict(index)


class Handler(BaseHTTPRequestHandler):
    def do_GET(self):
        self.reply(200, {'modell': MODEL})

    def do_POST(self):
        if self.path == '/suche':
            return self.suche()
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

    def suche(self):
        try:
            frage = str(json.loads(self.rfile.read(int(self.headers.get('Content-Length', 0))))['frage'])[:4000]
        except (ValueError, KeyError, TypeError):
            return self.reply(400, {'fehler': 'erwartet {"frage": "..."}'})
        try:
            idx = load_index()
        except (OSError, ValueError) as e:
            return self.reply(503, {'fehler': f'Index nicht lesbar: {e}'})
        if idx['modell'] != MODEL:
            return self.reply(409, {'fehler': f"Index mit anderem Modell berechnet ({idx['modell']})"})
        with lock:
            q = np.array(next(iter(model.query_embed([frage]))), dtype=np.float32)
        q /= max(float(np.linalg.norm(q)), 1e-9)
        # wie bisher im KI-Assistenten: Frage-Vektor auf 6 Stellen, Abschnitt-Vektoren float32, stabil sortiert
        scores = idx['matrix'] @ q.round(6).astype(np.float64)
        rang = idx['ids'][np.argsort(-scores, kind='stable')]
        self.reply(200, {'modell': MODEL, 'stand': idx['stand'], 'rang': rang.tolist()})

    def reply(self, status, data):
        body = json.dumps(data).encode()
        self.send_response(status)
        self.send_header('Content-Type', 'application/json')
        self.send_header('Content-Length', str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def log_message(self, *args):
        pass  # keine Fragen ins Log


def preload():
    try:
        load_index()
    except (OSError, ValueError):
        pass  # noch kein Index: wird bei der ersten Suche gelesen


threading.Thread(target=preload, daemon=True).start()  # erste Frage nach dem Start nicht warten lassen
ThreadingHTTPServer(('0.0.0.0', 8000), Handler).serve_forever()
