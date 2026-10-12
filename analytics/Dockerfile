FROM python:3.11-slim

ENV PYTHONDONTWRITEBYTECODE=1 PYTHONUNBUFFERED=1 \
    HF_HOME=/models/hf MODEL_DIR=/app/models

WORKDIR /app
RUN pip install --no-cache-dir torch --index-url https://download.pytorch.org/whl/cpu
COPY pyproject.toml ./
COPY soleglance_analytics ./soleglance_analytics
RUN pip install --no-cache-dir ".[research]"
# research/ is needed in the image for gated retraining (soleglance_analytics.mlops).
COPY research ./research

# Trained models seed the analytics-models volume on first start; feedback,
# the registry and retrained versions then persist in that volume.
COPY models ./models

RUN useradd --create-home analytics && mkdir -p /models/hf && chown -R analytics /models /app
USER analytics
EXPOSE 8001
# Fetch the trained model bundle if the models volume is empty, then serve.
CMD ["sh", "-c", "python -m soleglance_analytics.fetch_models && exec uvicorn soleglance_analytics.app:app --host 0.0.0.0 --port 8001 --workers 1"]
