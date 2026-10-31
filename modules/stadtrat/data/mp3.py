"""WAV (16 Bit, mono) → MP3 mit LAME (lameenc aus ../vendor-py, siehe scripts/run-setup.sh stadtrat-mp3).
Aufruf: python3 mp3.py <eingabe.wav> <ausgabe.mp3> [kbit/s]"""
import os
import sys
import wave

sys.path.insert(0, os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'vendor-py'))
import lameenc  # noqa: E402

with wave.open(sys.argv[1], 'rb') as w:
    if w.getsampwidth() != 2:
        sys.exit('nur 16 Bit')
    enc = lameenc.Encoder()
    enc.set_bit_rate(int(sys.argv[3]) if len(sys.argv) > 3 else 64)
    enc.set_in_sample_rate(w.getframerate())
    enc.set_channels(w.getnchannels())
    enc.set_quality(2)
    daten = enc.encode(w.readframes(w.getnframes())) + enc.flush()
with open(sys.argv[2], 'wb') as f:
    f.write(daten)
