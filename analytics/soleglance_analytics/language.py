"""Language identification for en / ur / ur-Latn / pcm.

General-purpose language identifiers label Roman Urdu as English (or as a
random language). We first check the script: mostly Perso-Arabic letters
means Urdu. Latin-script text is then classified as English, Roman Urdu or
Nigerian Pidgin by a character n-gram model trained by research/train_routing.py
(models/langid.joblib). If that model is absent, a lexicon of distinctive
Roman Urdu words is used instead (detect_lexicon, the baseline in the paper).
"""

from __future__ import annotations

import os
from functools import lru_cache
from pathlib import Path

from .text import normalise, script_ratio, tokenise

ENGLISH = "en"
URDU = "ur"
ROMAN_URDU = "ur-Latn"
PIDGIN = "pcm"

# Distinctive Nigerian Pidgin words (rare as standalone English tokens).
PIDGIN_MARKERS = frozenset("""
    dey wey wetin abeg sef una dem oya sabi pikin wahala jare ehn abi shey
    dis dat dem don nau naim wan wahala chop gist ogbonge yawa ginger palava
    comot belle kpai waka ment japa gbege biko oyibo nawa sha
""".split())

# Distinctive Roman Urdu words: frequent in Roman Urdu, rare as English tokens.
# Ambiguous forms such as "me", "main", "the", "to", "is" are deliberately excluded.
ROMAN_URDU_MARKERS = frozenset("""
    hai hain hy hn tha thi thay hua hui hoga hogi
    ka ki ke ko se mein mai par pe bhi aur nahi nahin nai na
    ye yeh wo woh kya kia kyun kyon kaise kese kaisa kesa
    abhi phir fir sab koi kuch sirf lekin magar agar
    mera meri mere tera teri tere apna apni apne hum tum aap ap
    karo karna karte karta karti kiya raha rahi rahe gaya gayi diya liya
    wala wali wale sakta sakti bohat bohot bahut bht acha achha achi acchi
    bura buri yaar yar bilkul zabardast bakwas ganda gandi pyar mohabbat
    shukriya mehngai hukumat awam mulk zindagi dost bhai behen allah inshallah mashallah
""".split())


@lru_cache(maxsize=1)
def _model():
    path = Path(os.environ.get("MODEL_DIR", Path(__file__).resolve().parent.parent / "models")) / "langid.joblib"
    if not path.exists():
        return None
    import joblib

    return joblib.load(path)["pipeline"]


def detect(text: str) -> str:
    """Trained character n-gram identifier, falling back to the lexicon."""
    arabic, latin = script_ratio(text)
    if arabic > 0.5:
        return URDU
    if latin == 0:
        return ENGLISH
    model = _model()
    if model is None:
        return detect_lexicon(text)
    return str(model.predict([normalise(text)])[0])


def detect_lexicon(text: str) -> str:
    """Script check plus Roman Urdu marker words (no trained model)."""
    arabic, latin = script_ratio(text)
    if arabic > 0.5:
        return URDU
    if latin == 0:
        return ENGLISH

    tokens = [t.lstrip("#") for t in tokenise(text)]
    words = [t for t in tokens if t.isalpha()]
    if not words:
        return ENGLISH

    pcm_hits = sum(1 for w in words if w in PIDGIN_MARKERS)
    hits = sum(1 for w in words if w in ROMAN_URDU_MARKERS)
    if pcm_hits > hits and pcm_hits >= 1 and (pcm_hits >= 2 or len(words) <= 6):
        return PIDGIN
    # Short texts need two markers; longer ones a 15% marker density.
    if hits >= 2 and hits / len(words) >= 0.15:
        return ROMAN_URDU
    if len(words) <= 3 and hits >= 1:
        return ROMAN_URDU
    return ENGLISH
