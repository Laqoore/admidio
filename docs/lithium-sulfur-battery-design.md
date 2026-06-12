# Lithium–Sulfur Battery — Engineering Design Specification

**Revision:** 1.0 (2026-06-12)
**Scope:** A complete, buildable design for a working rechargeable lithium–sulfur (Li–S)
cell, specified at two levels: (A) a CR2032 coin-cell demonstrator for validation, and
(B) a 1 Ah single-stack pouch cell. All materials are commercially available; all
processes are executable in a standard battery research lab with an argon glovebox.

---

## 1. Design overview and electrochemistry

A Li–S cell stores energy via the reversible conversion reaction:

```
S8 + 16 Li+ + 16 e-  ⇌  8 Li2S          E ≈ 2.15 V vs. Li/Li+
```

- **Theoretical sulfur capacity:** 1672 mAh/g (vs. ~170–200 mAh/g for LiFePO4/NMC)
- **Theoretical specific energy:** ~2600 Wh/kg (cell-level practical target: 300–350 Wh/kg)
- **Discharge profile:** two plateaus —
  - ~2.30 V: solid S8 → soluble long-chain polysulfides (Li2S8 → Li2S4), ¼ of capacity
  - ~2.10 V: Li2S4 → solid Li2S2/Li2S, ¾ of capacity
- **Voltage window:** **1.8 V – 2.6 V** (charge cutoff 2.6 V; discharge cutoff 1.8 V to
  protect the LiNO3 additive, which decomposes irreversibly below ~1.6 V)

The four failure mechanisms the design must defeat, and the countermeasure used here:

| Failure mechanism | Countermeasure in this design |
|---|---|
| Polysulfide shuttle (soluble Li2S4–Li2S8 migrate to anode, self-discharge, low Coulombic efficiency) | LiNO3 additive passivates Li anode; high-surface-area carbon host confines sulfur; controlled E/S ratio |
| Insulating active material (S8 and Li2S are electronic insulators) | Sulfur melt-infused into conductive Ketjenblack carbon (S never more than a few nm from carbon) |
| 80 % volume expansion of S → Li2S | Elastic CMC/SBR aqueous binder; calendering to only ~50 % porosity (not fully dense) |
| Li metal dendrites / anode pulverization | Excess Li (N/P ≥ 2.5), LiNO3-derived SEI, moderate current density (≤ 1 mA/cm²) |

---

## 2. Bill of materials

| # | Component | Specification | Example source |
|---|---|---|---|
| 1 | Sulfur | Sublimed sulfur, ≥99.5 %, powder | Sigma-Aldrich 84683 |
| 2 | Carbon host | Ketjenblack EC-600JD (BET ~1400 m²/g) | AkzoNobel / Lion |
| 3 | Conductive additive | Super P carbon black | Imerys / MTI |
| 4 | Binder | CMC (Na-carboxymethyl cellulose) + SBR latex | MTI |
| 5 | Cathode current collector | Carbon-coated Al foil, 16–20 µm | MTI |
| 6 | Anode | Li metal foil: 450 µm (coin) / 50 µm on 10 µm Cu (pouch) | China Energy Lithium / MTI |
| 7 | Separator | Celgard 2325 (PP/PE/PP trilayer, 25 µm) | Celgard |
| 8 | Electrolyte salt | LiTFSI, battery grade (≥99.9 %, H2O < 20 ppm) | Solvionic / Sigma |
| 9 | Solvents | 1,3-dioxolane (DOL) + 1,2-dimethoxyethane (DME), anhydrous | Sigma, dried over 4 Å sieves |
| 10 | Additive | LiNO3, battery grade, vacuum-dried 12 h @ 120 °C | Sigma 746754 |
| 11 | Cell hardware | CR2032 cases, spacers (0.5 mm SS), wave spring / pouch laminate + Al & Ni tabs | MTI |

**Electrolyte recipe (mix in glovebox, < 1 ppm H2O/O2):**

> **1.0 M LiTFSI in DOL:DME (1:1 by volume) + 2 wt % LiNO3**

Per 100 mL: 28.7 g LiTFSI, ~2.6 g LiNO3, balance DOL/DME. Stir 12 h; verify water
< 30 ppm by Karl Fischer titration before use.

---

## 3. Cathode design

### 3.1 Sulfur–carbon composite (melt diffusion)

1. Hand-grind sulfur : Ketjenblack at **70 : 30 by mass**; then ball-mill 30 min @ 300 rpm.
2. Seal in a PTFE-lined autoclave or glass vial under Ar; heat **155 °C for 12 h**
   (sulfur viscosity minimum → capillary infiltration into carbon pores).
3. Optional: 30 min at 200 °C under Ar to sublime surface sulfur.
4. Verify composition by thermogravimetric analysis (TGA, N2, to 400 °C): target
   68–72 wt % S.

### 3.2 Electrode slurry and coating

| Component | Dry mass fraction |
|---|---|
| S/C composite (70 % S) | 80 % |
| Super P | 10 % |
| CMC : SBR (1:1) | 10 % |

→ **Net sulfur fraction in electrode: 56 wt %.**

- Dissolve CMC in deionized water (aqueous processing — DOL/DME-compatible and avoids
  NMP); add Super P, mix; add S/C composite; planetary-mix to a smooth slurry
  (~40 % solids). Add SBR latex last (gentle mixing, 10 min).
- Doctor-blade onto carbon-coated Al foil. Wet-gap set for dry areal loading:
  - **Coin cell: 1.8 mg S/cm²** (single-sided)
  - **Pouch cell: 4.0 mg S/cm²** per side, double-sided
- Dry 50 °C / 12 h in air, then **vacuum 60 °C / 12 h** (never above 80 °C — sulfur
  sublimation). Calender to **~50 % porosity** (electrode density ≈ 0.9–1.0 g/cm³).
  Do **not** densify further: the pore volume is needed for electrolyte and for the
  S→Li2S expansion.

---

## 4. Cell-level design math

### 4.1 Coin cell (CR2032 demonstrator)

| Parameter | Value |
|---|---|
| Cathode disc | Ø 12 mm (1.13 cm²), 1.8 mg S/cm² → **2.03 mg S** |
| Rated capacity (1100 mAh/g practical) | **2.24 mAh** |
| Anode | Li disc Ø 14 mm × 450 µm (massive excess — reference design) |
| Separator | Celgard 2325, Ø 19 mm |
| Electrolyte volume | E/S = **10 µL per mg S** → 20 µL |
| 0.1C current | 0.224 mA (≈ 0.20 mA/cm²) |

### 4.2 Pouch cell (1 Ah, single stack)

Electrode footprint 56 × 43 mm (24 cm² per face).

- Practical reversible capacity at 0.1C with E/S = 5 µL/mg: **1000 mAh/g S**
- Sulfur required: 1.0 Ah ÷ 1.0 Ah/g = **1.0 g S** → 1.0 g / 4.0 mg/cm² = 250 cm² of
  coated face → **6 double-sided cathodes** (288 cm², 1.15 g S → 1.15 Ah nameplate,
  15 % design margin)
- **7 anodes**: 50 µm Li both sides of Cu. Areal Li capacity 50 µm ≈ 10.3 mAh/cm² vs.
  cathode 4.0 mAh/cm² → **N/P = 2.6** ✔
- Electrolyte: 5 µL/mg × 1150 mg = **5.75 mL** (this lean ratio is the key to energy
  density; below 4 µL/mg cycle life collapses, above 7 µL/mg energy density collapses)

**Mass budget (estimated):**

| Item | Mass |
|---|---|
| Cathodes (composite + Al) | 3.4 g |
| Anodes (Li + Cu) | 4.1 g |
| Separator | 0.9 g |
| Electrolyte (ρ ≈ 1.15 g/mL) | 6.6 g |
| Pouch + tabs | 3.0 g |
| **Total** | **≈ 18 g** |

→ 1.0 Ah × 2.1 V avg = 2.1 Wh / 18 g ≈ **117 Wh/kg** for this small-format cell;
the same stack scaled to 10+ Ah multi-layer format projects to **280–330 Wh/kg**
(packaging overhead amortizes; this is consistent with published Li–S pouch results).

---

## 5. Assembly procedure (Ar glovebox, < 1 ppm H2O / O2)

**Coin cell stack (bottom → top):** cathode case → cathode disc (coating up) → 10 µL
electrolyte → separator → 10 µL electrolyte → Li disc → SS spacer → wave spring →
anode cap. Crimp at ~0.8 t. Rest **6 h at open circuit** before cycling (wetting +
LiNO3 SEI formation).

**Pouch cell:** Z-fold or stack-and-tab; ultrasonic-weld Al tab (cathode) and Ni tab
(anode); triple-side heat-seal; inject electrolyte; vacuum-seal at −90 kPa. Rest 12 h.
After formation cycle 1, degas (Li–S evolves some gas from LiNO3 reduction): cut open
the gas pocket in the glovebox and final-seal.

---

## 6. Test and acceptance protocol

| Step | Condition | Pass criterion |
|---|---|---|
| OCV after rest | — | 2.1–2.5 V, stable ±5 mV/h |
| Formation | 1 cycle @ C/20, 1.8–2.6 V | First discharge ≥ 1200 mAh/g S |
| Rate check | C/10, 5 cycles | ≥ 1000 mAh/g; two plateaus visible at 2.3 / 2.1 V |
| Coulombic efficiency | C/10 | ≥ 98 % by cycle 5 (LiNO3 working; < 95 % = shuttle, reject) |
| Cycle life | C/5 charge / C/5 discharge | ≥ 80 % capacity retention at cycle 100 |
| Self-discharge | 72 h rest at 100 % SOC | < 5 % capacity loss |

**Diagnostics if a criterion fails**

- *CE < 95 %:* shuttle current — verify LiNO3 concentration and ≥1.8 V cutoff; check
  separator for pinholes.
- *Single sloped plateau / low capacity:* poor S–C contact — re-verify melt-diffusion
  TGA and calendering.
- *Sudden death at cycle 30–60:* electrolyte depletion — E/S too lean or excessive Li
  corrosion; raise E/S one step, reduce current density.
- *Soft short (noisy voltage on charge):* Li dendrite — reduce charge rate to C/10.

---

## 7. Safety

- Li metal + ether electrolyte is flammable: all assembly under Ar; cycle cells in a
  ventilated, fire-rated chamber; never charge above 2.6 V or below 0 °C.
- DOL can peroxidize and polymerize: store inhibited or freshly distilled, over sieves.
- Failed/spent cells: discharge to 1.8 V, then deactivate Li by slow controlled
  immersion in isopropanol before aqueous disposal per local regulations.

---

## 8. Design summary

| Cell | Nameplate | Voltage | Key ratios |
|---|---|---|---|
| CR2032 demonstrator | 2.2 mAh | 2.1 V avg, 1.8–2.6 V | E/S 10 µL/mg, S loading 1.8 mg/cm² |
| 1 Ah pouch | 1.15 Ah | 2.1 V avg, 1.8–2.6 V | E/S 5 µL/mg, S loading 4 mg/cm²/side, N/P 2.6 |

The coin cell validates chemistry (composite, electrolyte, voltage window); the pouch
cell validates the engineering ratios (lean electrolyte, N/P, stack pressure) that
determine whether a Li–S design *works* outside a lab curve. Build A first; promote
the exact same materials set to B once A passes §6.
