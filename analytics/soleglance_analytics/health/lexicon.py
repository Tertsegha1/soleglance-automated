"""Disease and symptom lexicons for English, Nigerian Pidgin, Urdu and Roman Urdu.

Each concept maps to surface forms. Multi-word forms are matched as
contiguous token sequences after tokenisation (text.tokenise), so they work
for both Latin and Perso-Arabic script. Pidgin mostly borrows English disease
names, so its entries focus on symptom descriptions ("body dey hot").

The lists were compiled from WHO/NCDC/Pakistan NIH public-facing material and
common usage; they are a starting point to be extended by native speakers
(see paper/health-annotation-guidelines.md).
"""

from __future__ import annotations

# concept id -> {"label": display name, "kind": disease|symptom, "terms": [...], "priority": bool}
CONCEPTS: dict[str, dict] = {
    # --- Priority diseases for Nigeria and Pakistan --------------------------------
    "lassa_fever": {"label": "Lassa fever", "kind": "disease", "priority": True, "terms": [
        "lassa", "lassa fever", "lassa virus", "lasa fever", "lassa feva",
        "لاسا", "لاسا بخار",
    ]},
    "cholera": {"label": "Cholera", "kind": "disease", "priority": True, "terms": [
        "cholera", "kolera", "haiza", "haiza ki wabaa", "ہیضہ", "ہیضے",
    ]},
    "meningitis": {"label": "Meningitis", "kind": "disease", "priority": True, "terms": [
        "meningitis", "cerebrospinal meningitis", "csm", "gardan tor bukhar", "گردن توڑ بخار",
    ]},
    "dengue": {"label": "Dengue", "kind": "disease", "priority": True, "terms": [
        "dengue", "dengue fever", "dengi", "dengu", "ڈینگی", "ڈینگو", "ڈینگی بخار",
    ]},
    "malaria": {"label": "Malaria", "kind": "disease", "priority": False, "terms": [
        "malaria", "maleria", "malaria fever", "ملیریا",
    ]},
    "typhoid": {"label": "Typhoid", "kind": "disease", "priority": False, "terms": [
        "typhoid", "typhoid fever", "taifaid", "miyadi bukhar", "ٹائیفائیڈ", "میعادی بخار",
    ]},
    "measles": {"label": "Measles", "kind": "disease", "priority": True, "terms": [
        "measles", "khasra", "خسرہ",
    ]},
    "diphtheria": {"label": "Diphtheria", "kind": "disease", "priority": True, "terms": [
        "diphtheria", "khunnaq", "خناق",
    ]},
    "mpox": {"label": "Mpox", "kind": "disease", "priority": True, "terms": [
        "mpox", "monkeypox", "monkey pox", "منکی پاکس", "ایم پاکس",
    ]},
    "yellow_fever": {"label": "Yellow fever", "kind": "disease", "priority": True, "terms": [
        "yellow fever", "zard bukhar", "زرد بخار",
    ]},
    "polio": {"label": "Polio", "kind": "disease", "priority": True, "terms": [
        "polio", "poliomyelitis", "پولیو",
    ]},
    "covid19": {"label": "COVID-19", "kind": "disease", "priority": False, "terms": [
        "covid", "covid19", "covid 19", "coronavirus", "corona virus", "corona", "کورونا", "کووڈ",
    ]},
    "tuberculosis": {"label": "Tuberculosis", "kind": "disease", "priority": False, "terms": [
        # "tb" is omitted: in Roman Urdu it is shorthand for "tab" (then).
        "tuberculosis", "tapedik", "tap e diq", "ٹی بی", "تپ دق",
    ]},
    "influenza": {"label": "Influenza", "kind": "disease", "priority": False, "terms": [
        "influenza", "flu", "bird flu", "نزلہ زکام", "فلو",
    ]},
    "hepatitis": {"label": "Hepatitis", "kind": "disease", "priority": False, "terms": [
        "hepatitis", "yarkan", "kala yarkan", "یرقان", "ہیپاٹائٹس",
    ]},
    # --- Common non-outbreak conditions (general health-mention detection) ----------
    "cancer": {"label": "Cancer", "kind": "disease", "priority": False, "terms": [
        "cancer", "tumour", "tumor", "leukemia", "leukaemia", "kenser", "کینسر", "سرطان",
    ]},
    "diabetes": {"label": "Diabetes", "kind": "disease", "priority": False, "terms": [
        "diabetes", "diabetic", "sugar ki bimari", "sugar dey worry", "ذیابیطس", "شوگر",
    ]},
    "cardiovascular": {"label": "Heart attack / stroke", "kind": "disease", "priority": False, "terms": [
        "heart attack", "cardiac arrest", "stroke", "hypertension", "high blood pressure", "high bp",
        "dil ka dora", "blood pressure", "دل کا دورہ", "فالج", "بلڈ پریشر",
    ]},
    "respiratory": {"label": "Asthma / pneumonia", "kind": "disease", "priority": False, "terms": [
        "asthma", "pneumonia", "bronchitis", "dama", "دمہ", "نمونیا",
    ]},
    "mental_health": {"label": "Mental health condition", "kind": "disease", "priority": False, "terms": [
        "depression", "anxiety", "bipolar", "ptsd", "ocd", "schizophrenia", "panic attack",
        "ڈپریشن", "ذہنی دباؤ",
    ]},
    "neurological": {"label": "Neurological condition", "kind": "disease", "priority": False, "terms": [
        "migraine", "alzheimer", "alzheimers", "parkinson", "parkinsons", "epilepsy", "seizure", "seizures",
        "مرگی",
    ]},
    "allergy": {"label": "Allergy", "kind": "disease", "priority": False, "terms": [
        "allergy", "allergies", "allergic", "الرجی",
    ]},
    # --- Symptoms -------------------------------------------------------------------
    "fever": {"label": "Fever", "kind": "symptom", "priority": False, "terms": [
        "fever", "high temperature", "feverish", "body dey hot", "body hot", "my body dey hot",
        "bukhar", "bukhaar", "بخار",
    ]},
    "diarrhoea": {"label": "Diarrhoea", "kind": "symptom", "priority": False, "terms": [
        "diarrhoea", "diarrhea", "running stomach", "stooling", "belle dey run", "belle run",
        # Bare "dast"/"دست" is omitted: it also means "hand" (e.g. Dast-e-Saba).
        "dast lag", "dast lage", "dast lagay", "dast aa", "ishaal", "اسہال", "دست لگ", "پیچش",
    ]},
    "vomiting": {"label": "Vomiting", "kind": "symptom", "priority": False, "terms": [
        "vomiting", "vomit", "vomited", "throwing up", "dey vomit", "dey throw up",
        "ulti", "ultiyan", "الٹی", "الٹیاں", "قے",
    ]},
    "bleeding": {"label": "Unusual bleeding", "kind": "symptom", "priority": False, "terms": [
        "bleeding", "bleeding from", "blood from nose", "nose bleeding", "dey bleed",
        "khoon behna", "خون بہنا", "خون آنا",
    ]},
    "headache": {"label": "Headache", "kind": "symptom", "priority": False, "terms": [
        "headache", "severe headache", "head dey pain", "my head dey pain",
        "sar dard", "sir dard", "سر درد",
    ]},
    "rash": {"label": "Rash", "kind": "symptom", "priority": False, "terms": [
        "rash", "rashes", "skin rash", "daane", "دانے",
    ]},
    "stiff_neck": {"label": "Stiff neck", "kind": "symptom", "priority": False, "terms": [
        "stiff neck", "neck stiffness", "neck dey pain", "gardan akar", "گردن اکڑ",
    ]},
    "cough": {"label": "Cough", "kind": "symptom", "priority": False, "terms": [
        "cough", "coughing", "dey cough", "khansi", "کھانسی",
    ]},
    "body_pain": {"label": "Body/joint pain", "kind": "symptom", "priority": False, "terms": [
        "body pain", "joint pain", "body dey pain me", "body dey pain", "jism dard", "jor dard",
        "جسم درد", "جوڑوں کا درد", "ہڈی توڑ",
    ]},
    "dehydration": {"label": "Dehydration", "kind": "symptom", "priority": False, "terms": [
        "dehydration", "dehydrated", "pani ki kami", "پانی کی کمی",
    ]},
}

# Communicable diseases: their names are almost never used figuratively, so a
# lexicon match on them is accepted without the figurative-use classifier.
COMMUNICABLE = frozenset({
    "lassa_fever", "cholera", "meningitis", "dengue", "malaria", "typhoid", "measles", "diphtheria",
    "mpox", "yellow_fever", "polio", "covid19", "tuberculosis", "influenza", "hepatitis",
})

# Words that indicate a report of illness or an outbreak (used as features).
OUTBREAK_CUES = frozenset("""
    outbreak outbreaks cases case deaths died death killed infected infection spread spreading
    epidemic hospitalised hospitalized admitted isolation confirmed suspected surge
    wabaa wabai mareez mareezon halak afrad
    وبا مریض مریضوں ہلاک ہلاکتیں متاثر کیسز اموات
""".split())
