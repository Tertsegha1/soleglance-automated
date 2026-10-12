"""Text normalisation and tokenisation shared by every component.

Handles English, Urdu (Arabic script) and Roman Urdu (Urdu written in
Latin script), which is how most Pakistani social media users write.
"""

from __future__ import annotations

import re
import unicodedata

from sklearn.feature_extraction.text import ENGLISH_STOP_WORDS

URL_RE = re.compile(r"https?://\S+|www\.\S+", re.IGNORECASE)
MENTION_RE = re.compile(r"@\w+")
HASHTAG_RE = re.compile(r"#(\w+)", re.UNICODE)
WS_RE = re.compile(r"\s+")

# Arabic-script block used for Urdu (plus presentation forms).
ARABIC_CHARS = r"؀-ۿݐ-ݿﭐ-﷿ﹰ-﻿"
ARABIC_RE = re.compile(f"[{ARABIC_CHARS}]")
LATIN_RE = re.compile(r"[A-Za-z]")
TOKEN_RE = re.compile(rf"#?[{ARABIC_CHARS}]+|#?[a-z0-9]+(?:'[a-z]+)?", re.UNICODE)

# Frequent Roman Urdu function words and fillers. Kept out of trend terms.
ROMAN_URDU_STOPWORDS = frozenset("""
    hai hain hy hn ha he tha thi thay the thy ho hon hona hua hui huay hue
    ka ki ke ko se mein mai main me par pe tak bhi aur ya to tou na nahi nahin nai
    ye yeh wo woh is us in un jo jis jin kya kia kyun kyon kaise kese kaisa kesa
    ab abhi jab tab phir fir sab koi kuch bas sirf lekin magar agar
    mera meri mere tera teri tere apna apni apne hum tum aap ap unka unki iska uska
    kar karo karna karte karta karti kiya kiye raha rahi rahe gaya gayi gaye diya liya
    wala wali wale ga gi ge ne hoga hogi hoge sakta sakti sakte
""".split())

URDU_STOPWORDS = frozenset("""
    ہے ہیں تھا تھی تھے ہو ہوں کا کی کے کو سے میں پر تک بھی اور یا تو نہ نہیں
    یہ وہ اس ان جو جس کیا کیوں کیسے اب جب تب پھر سب کوئی کچھ لیکن مگر اگر
    میرا میری میرے تیرا ہم تم آپ اپنا اپنی اپنے نے گا گی گے ہی رہا رہی رہے گیا گئی
""".split())

# Nigerian Pidgin function words and particles.
PIDGIN_STOPWORDS = frozenset("""
    dey na wey don una dem am sef abi o oo jare ehn sha naim nau dis dat
    go come make wetin abeg oya fit no say for wan im
""".split())

STOPWORDS = frozenset(ENGLISH_STOP_WORDS) | ROMAN_URDU_STOPWORDS | URDU_STOPWORDS | PIDGIN_STOPWORDS | {"rt", "amp", "http", "https", "user"}


def normalise(text: str) -> str:
    """Canonical form for models: NFKC, URLs/mentions masked, whitespace collapsed.

    The URL and mention placeholders follow the TweetEval/XLM-T convention.
    """
    text = unicodedata.normalize("NFKC", text or "")
    text = URL_RE.sub("http", text)
    text = MENTION_RE.sub("@user", text)
    return WS_RE.sub(" ", text).strip()


def tokenise(text: str) -> list[str]:
    """Lower-cased word tokens. Hashtags keep their leading '#'."""
    text = URL_RE.sub(" ", unicodedata.normalize("NFKC", text or ""))
    text = MENTION_RE.sub(" ", text)
    return TOKEN_RE.findall(text.lower())


def content_tokens(text: str) -> list[str]:
    """Tokens that can carry topical meaning (no stopwords, numbers or 1-2 letter Latin words)."""
    out = []
    for tok in tokenise(text):
        bare = tok.lstrip("#")
        if not bare or bare in STOPWORDS or bare.isdigit():
            continue
        if ARABIC_RE.match(bare):
            if len(bare) < 2:
                continue
        elif len(bare) < 3:
            continue
        out.append(tok)
    return out


def script_ratio(text: str) -> tuple[float, float]:
    """Share of letters in Arabic script and in Latin script."""
    arabic = len(ARABIC_RE.findall(text))
    latin = len(LATIN_RE.findall(text))
    total = arabic + latin
    if total == 0:
        return 0.0, 0.0
    return arabic / total, latin / total
