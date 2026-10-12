"""Pre-trained transformer sentiment classifiers used as released.

* XLM-T (Barbieri et al., 2022): multilingual tweets in eight languages,
  not including Urdu, Roman Urdu or Nigerian Pidgin. Its English training
  data overlap with SemEval-2017, so English results are optimistic.
* AfriBERTa-NaijaSenti (Adelani et al.; Muhammad et al., 2022): fine-tuned
  on NaijaSenti, which includes Nigerian Pidgin. NaijaSenti and AfriSenti
  share tweets, so its Pidgin results on AfriSenti are optimistic.
"""

from __future__ import annotations

from typing import Sequence

import numpy as np

from ..text import normalise
from .base import LABELS, Prediction, from_proba

MODEL_ID = "cardiffnlp/twitter-xlm-roberta-base-sentiment"
AFRIBERTA_ID = "Davlan/naija-twitter-sentiment-afriberta-large"


class HFSentimentBackend:
    def __init__(self, model_id: str, name: str, batch_size: int = 32) -> None:
        import torch
        from transformers import AutoModelForSequenceClassification, AutoTokenizer

        self.name = name
        self.torch = torch
        self.tokenizer = AutoTokenizer.from_pretrained(model_id)
        self.model = AutoModelForSequenceClassification.from_pretrained(model_id).eval()
        id2label = {i: l.lower() for i, l in self.model.config.id2label.items()}
        self.order = [next(i for i, l in id2label.items() if l == want) for want in LABELS]
        self.batch_size = batch_size

    def predict_proba(self, texts: Sequence[str]) -> np.ndarray:
        out = []
        with self.torch.inference_mode():
            for i in range(0, len(texts), self.batch_size):
                batch = [normalise(t) for t in texts[i : i + self.batch_size]]
                enc = self.tokenizer(batch, padding=True, truncation=True, max_length=128, return_tensors="pt")
                probs = self.torch.softmax(self.model(**enc).logits, dim=-1).numpy()
                out.append(probs[:, self.order])
        return np.vstack(out) if out else np.zeros((0, 3))

    def predict(self, texts: Sequence[str]) -> list[Prediction]:
        return from_proba(self.predict_proba(texts))


class XlmtBackend(HFSentimentBackend):
    def __init__(self, model_id: str = MODEL_ID, batch_size: int = 32) -> None:
        super().__init__(model_id, "xlm-t", batch_size)


class AfribertaBackend(HFSentimentBackend):
    def __init__(self, model_id: str = AFRIBERTA_ID, batch_size: int = 16) -> None:
        super().__init__(model_id, "afriberta-naijasenti", batch_size)
