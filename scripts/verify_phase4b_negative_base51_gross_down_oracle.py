"""Check MOD arithmetic independently; this is not a qualified monthly tax result."""
from decimal import Decimal, localcontext, ROUND_HALF_EVEN
from pathlib import Path
import hashlib
import json

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-negative-base51-current-bases-preparation-20261009'
source = ROOT / 'resources/fiscal/sources/official/dere-observed-20261005-mod.pdf'


def value(number):
    return format(number.quantize(Decimal('0.01'), rounding=ROUND_HALF_EVEN), 'f')


with localcontext() as context:
    context.prec = 50
    # Physical page47: published example with IS. It does not qualify IS for financial services.
    general_factor = (1 + Decimal('25') / 100) * (1 + (Decimal('8') + Decimal('2')) / 100)
    transition_factor = (1 + Decimal('25') / 100) * (1 + Decimal('8') / 100)
    general_is_base = Decimal('123750.00') / general_factor
    transition_is_base = Decimal('13500.00') / transition_factor
    assert general_factor == Decimal('1.375') and transition_factor == Decimal('1.350')
    assert general_is_base == Decimal('90000') and transition_is_base == Decimal('10000')
    general_ibs_cbs = general_is_base * Decimal('1.25')
    transition_ibs = transition_is_base * Decimal('1.25')
    ibs_base = general_ibs_cbs + transition_ibs
    cbs_base = general_ibs_cbs
    ibs = ibs_base * Decimal('0.08')
    cbs = cbs_base * Decimal('0.02')
    selective = (general_is_base + transition_is_base) * Decimal('0.25')
    assert (ibs_base, cbs_base, ibs, cbs, selective) == tuple(map(Decimal, [125000, 112500, 10000, 2250, 25000]))
    assert ibs_base + ibs + cbs == Decimal('137250.00')
    # A sensitivity calculation, explicitly conditional on proving price/composition and no transition.
    hypothetical_base = Decimal('900.00') / Decimal('1.01')
    report = {
        'status': 'OFFICIAL_MOD_EXAMPLE_ARITHMETIC_REPRODUCED_NOT_MONTHLY_ENGINE_ACCEPTANCE',
        'source': {'path': source.relative_to(ROOT).as_posix(),
                   'sha256': hashlib.sha256(source.read_bytes()).hexdigest(), 'physical_page': 47},
        'official_example': {'general_gross': '123750.00', 'transition_gross': '13500.00',
                             'general_factor': '1.375', 'transition_factor': '1.350',
                             'ibs_base': value(ibs_base), 'cbs_base': value(cbs_base),
                             'ibs': value(ibs), 'cbs': value(cbs), 'selective_tax': value(selective)},
        'financial_is_incidence_qualified_by_this_example': False,
        'conditional_sensitivity': {
            'gross_input': '900.00', 'ibs_percent': '0.10', 'cbs_percent': '0.90',
            'net_base_unrounded': format(hypothetical_base, 'f'),
            'net_base_display_only': value(hypothetical_base),
            'price_composition_and_absence_of_transition_proven': False,
            'applied_to_snapshot_or_tax_result': False},
        'monthly_engine_implementation_complete': False,
        'production_ready': False,
    }
path = E / 'gross-down-independent-oracle.json'
raw = json.dumps(report, ensure_ascii=False, indent=2) + '\n'
if path.exists():
    assert path.read_text(encoding='utf-8') == raw
else:
    with path.open('x', encoding='utf-8') as stream:
        stream.write(raw)
print('PASS: published MOD example independently reproduced; IBS/CBS segregation and embedded-tax conservation')
