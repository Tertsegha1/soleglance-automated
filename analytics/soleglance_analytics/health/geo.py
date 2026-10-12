"""Gazetteer for resolving place names to first-level regions (ISO 3166-2).

Covers all 36 Nigerian states plus the FCT and Pakistan's provinces and
territories, each with its capital and major cities. Ambiguous English
words (e.g. "Kogi" is unambiguous but "Niger" is also a country, "Plateau"
a common noun) are only matched in their unambiguous multi-word forms.
"""

from __future__ import annotations

# region code -> (country, display name, [surface forms])
REGIONS: dict[str, tuple[str, str, list[str]]] = {
    # Nigeria
    "NG-AB": ("NG", "Abia", ["abia", "umuahia", "aba"]),
    "NG-AD": ("NG", "Adamawa", ["adamawa", "yola", "mubi"]),
    "NG-AK": ("NG", "Akwa Ibom", ["akwa ibom", "uyo", "eket"]),
    "NG-AN": ("NG", "Anambra", ["anambra", "awka", "onitsha", "nnewi"]),
    "NG-BA": ("NG", "Bauchi", ["bauchi", "azare"]),
    "NG-BY": ("NG", "Bayelsa", ["bayelsa", "yenagoa"]),
    "NG-BE": ("NG", "Benue", ["benue", "makurdi", "gboko", "otukpo"]),
    "NG-BO": ("NG", "Borno", ["borno", "maiduguri"]),
    "NG-CR": ("NG", "Cross River", ["cross river", "calabar"]),
    "NG-DE": ("NG", "Delta", ["delta state", "asaba", "warri", "sapele"]),
    "NG-EB": ("NG", "Ebonyi", ["ebonyi", "abakaliki"]),
    "NG-ED": ("NG", "Edo", ["edo", "edo state", "benin city", "irrua", "auchi", "ekpoma"]),
    "NG-EK": ("NG", "Ekiti", ["ekiti", "ado ekiti"]),
    "NG-EN": ("NG", "Enugu", ["enugu", "nsukka"]),
    "NG-FC": ("NG", "Federal Capital Territory", ["fct", "abuja", "gwagwalada"]),
    "NG-GO": ("NG", "Gombe", ["gombe"]),
    "NG-IM": ("NG", "Imo", ["imo state", "owerri"]),
    "NG-JI": ("NG", "Jigawa", ["jigawa", "dutse"]),
    "NG-KD": ("NG", "Kaduna", ["kaduna", "zaria"]),
    "NG-KN": ("NG", "Kano", ["kano"]),
    "NG-KT": ("NG", "Katsina", ["katsina"]),
    "NG-KE": ("NG", "Kebbi", ["kebbi", "birnin kebbi"]),
    "NG-KO": ("NG", "Kogi", ["kogi", "lokoja"]),
    "NG-KW": ("NG", "Kwara", ["kwara", "ilorin"]),
    "NG-LA": ("NG", "Lagos", ["lagos", "ikeja", "lekki", "surulere", "ikorodu", "yaba"]),
    "NG-NA": ("NG", "Nasarawa", ["nasarawa", "lafia", "keffi"]),
    "NG-NI": ("NG", "Niger", ["niger state", "minna", "bida"]),
    "NG-OG": ("NG", "Ogun", ["ogun", "abeokuta", "sagamu", "ijebu ode"]),
    "NG-ON": ("NG", "Ondo", ["ondo", "akure", "owo", "ondo state"]),
    "NG-OS": ("NG", "Osun", ["osun", "osogbo", "ile ife", "ilesa"]),
    "NG-OY": ("NG", "Oyo", ["oyo", "ibadan", "ogbomoso"]),
    "NG-PL": ("NG", "Plateau", ["plateau state", "jos"]),
    "NG-RI": ("NG", "Rivers", ["rivers state", "port harcourt", "portharcourt"]),
    "NG-SO": ("NG", "Sokoto", ["sokoto"]),
    "NG-TA": ("NG", "Taraba", ["taraba", "jalingo"]),
    "NG-YO": ("NG", "Yobe", ["yobe", "damaturu"]),
    "NG-ZA": ("NG", "Zamfara", ["zamfara", "gusau"]),
    # Pakistan
    "PK-PB": ("PK", "Punjab", ["punjab", "lahore", "rawalpindi", "faisalabad", "multan", "gujranwala",
                                "sialkot", "bahawalpur", "sargodha",
                                "پنجاب", "لاہور", "راولپنڈی", "فیصل آباد", "ملتان", "گوجرانوالہ", "سیالکوٹ"]),
    "PK-SD": ("PK", "Sindh", ["sindh", "karachi", "hyderabad", "sukkur", "larkana", "mirpurkhas",
                               "سندھ", "کراچی", "حیدرآباد", "سکھر", "لاڑکانہ"]),
    "PK-KP": ("PK", "Khyber Pakhtunkhwa", ["khyber pakhtunkhwa", "kpk", "peshawar", "mardan", "abbottabad",
                                           "swat", "kohat", "خیبر پختونخوا", "پشاور", "مردان", "ایبٹ آباد", "سوات"]),
    "PK-BA": ("PK", "Balochistan", ["balochistan", "quetta", "gwadar", "turbat", "بلوچستان", "کوئٹہ", "گوادر"]),
    "PK-IS": ("PK", "Islamabad Capital Territory", ["islamabad", "اسلام آباد"]),
    "PK-GB": ("PK", "Gilgit-Baltistan", ["gilgit", "gilgit baltistan", "skardu", "گلگت", "سکردو"]),
    "PK-JK": ("PK", "Azad Jammu and Kashmir", ["azad kashmir", "ajk", "muzaffarabad", "mirpur", "آزاد کشمیر", "مظفرآباد"]),
}

COUNTRY_NAMES = {
    "NG": ["nigeria", "naija", "nigerian", "نائیجیریا"],
    "PK": ["pakistan", "pakistani", "پاکستان"],
}
