# Lokale Embeddings für die Stadtwissen-Suche des KI-Assistenten (statt eines Cloud-Dienstes)
FROM python:3.13-slim
RUN pip install --no-cache-dir fastembed==0.7.3
COPY server.py /server.py
ENV FASTEMBED_CACHE_PATH=/cache
CMD ["python", "-u", "/server.py"]
