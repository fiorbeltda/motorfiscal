"""Check independent balances and outcomes before recording immutable evidence."""
import hashlib
import json
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
VERSION = '2026.10.02-10'
BUNDLE = f'bundles/consumption-phase4b-{VERSION}.json'


def execute(args):
    process = subprocess.run(['docker', 'compose', 'exec', '-T', 'app', 'php', 'artisan', *args],
                             cwd=ROOT, capture_output=True, encoding='utf-8', check=False)
    value = json.loads(process.stdout)
    if 'error' in value:
        raise RuntimeError(value)
    return value


def save(path, value):
    target = ROOT / path
    payload = json.dumps(value, ensure_ascii=False, indent=2) + '\n'
    if target.exists() and target.read_text(encoding='utf-8') != payload:
        raise RuntimeError(f'Refusing to overwrite {target}')
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(payload, encoding='utf-8')


negative = {
    'wrong-network-type': ('INVALID', 'RTC_COOPERATIVE_ZERO_NETWORK_PARTIES_CONFLICT'),
    'missing-network-proof': ('ACTION_REQUIRED', 'RTC_COOPERATIVE_ZERO_NETWORK_EVIDENCE_MISSING:acquirer_registry_evidence_id'),
    'wrong-bank-participant': ('INVALID', 'RTC_COOPERATIVE_ZERO_BANK_PARTICIPANT_CONFLICT'),
    'missing-bank-proof': ('ACTION_REQUIRED', 'RTC_COOPERATIVE_ZERO_NETWORK_EVIDENCE_MISSING:bank_participation_evidence_id'),
    'transfer-missing': ('ACTION_REQUIRED', 'RTC_COOPERATIVE_TRANSFER_PROOF_MISSING'),
    'transfer-wrong-owner': ('INVALID', 'RTC_COOPERATIVE_TRANSFER_CREDIT_ENTRY_CONFLICT'),
    'transfer-overdrawn': ('INVALID', 'RTC_COOPERATIVE_TRANSFER_TRANSFER_EXCEEDS_AVAILABLE_CREDIT'),
    'transfer-unrelated': ('INVALID', 'RTC_COOPERATIVE_TRANSFER_CREDIT_NOT_RELATED_TO_SUPPLY'),
    'transfer-duplicate': ('INVALID', 'RTC_COOPERATIVE_TRANSFER_DUPLICATE_DOCUMENT_COMPONENT'),
    'transfer-future-date': ('INVALID', 'RTC_COOPERATIVE_TRANSFER_APPROPRIATION_DATE_CONFLICT'),
    'transfer-future-observation': ('INVALID', 'RTC_COOPERATIVE_TRANSFER_OBSERVATION_CONFLICT'),
    'transfer-already-recorded': ('INVALID', 'RTC_COOPERATIVE_TRANSFER_TRANSFER_ALREADY_RECORDED'),
    'transfer-wrong-route': ('INVALID', 'RTC_COOPERATIVE_TRANSFER_MEMBER_SUPPLY_ROUTE_REQUIRED'),
    'transfer-presumed-unqualified': ('ACTION_REQUIRED', 'RTC_COOPERATIVE_TRANSFER_PRESUMED_CREDIT_NOT_QUALIFIED'),
    'transfer-missing-regulatory-proof': ('ACTION_REQUIRED', 'RTC_COOPERATIVE_TRANSFER_EVIDENCE_MISSING:transfer_regulatory_validation_evidence_id'),
}
positive = ['single-central', 'federation-confederation', 'bank-participant',
            'transfer-partial', 'transfer-full', 'transfer-presumed']
snapshots = ['single-central', 'bank-participant', 'transfer-partial', 'transfer-full']
for name in positive + list(negative):
    full_name = f'integrated-cooperative-network-transfer-{name}-2026'
    prefix = f'storage/app/phase4b-cooperative-network-transfer-{name}-20261002-10'
    snapshot = prefix + '-snapshot.json'
    args = ['fiscal:scenario:run', f'resources/fiscal/scenarios/consumption-integrated-{VERSION}/{full_name}.json', f'--bundle-manifest={BUNDLE}']
    if name in snapshots and not (ROOT / snapshot).exists():
        args.append(f'--snapshot={snapshot}')
    value = execute(args)
    if name in positive:
        assert value['status'] == 'VALID_WITH_WARNINGS' and value['missing_facts'] == [], value
        rtc = value['decision']['consumption_reform']
        decision = rtc['decision']
        assert rtc['reason_codes'] == ['RTC_COOPERATIVE_ZERO_RATE']
        assert decision['classification']['cclasstrib'] == '200020'
        assert decision['base']['taxable'] == '1000.00'
        assert decision['ibs']['nominal_amount'] == decision['cbs']['nominal_amount'] == '0.00'
        assert value['decision']['iss']['calculation']['tax_amount'] == '50.00'
        assert value['decision']['federal_withholdings']['status'] == 'NOT_REQUESTED'
        if name.startswith('transfer-'):
            transfer = decision['treatment']['credit_transfer']
            full = name == 'transfer-full'
            assert transfer['ibs_available'] == '70.00' and transfer['cbs_available'] == '600.00'
            assert transfer['ibs_requested'] == ('70.00' if full else '50.00')
            assert transfer['cbs_requested'] == ('600.00' if full else '500.00')
            assert transfer['credits'][0]['remaining_after_requested_transfer'] == ('0.00' if full else '20.00')
            assert transfer['credits'][1]['remaining_after_requested_transfer'] == ('0.00' if full else '100.00')
            assert all(entry['expires_on'] == '2031-10-01' for entry in transfer['credits'])
            assert transfer['transfer_performed'] is False and transfer['posting_performed'] is False
        if name in snapshots:
            save(prefix + '-result.json', value)
            assert execute(['fiscal:decision:replay', snapshot])['replay_matches']
            report_path, candidate = prefix + '-xml-report.json', prefix + '-candidate.xml'
            if (ROOT / report_path).exists():
                report = json.loads((ROOT / report_path).read_text(encoding='utf-8'))
            else:
                report = execute(['fiscal:dps:project-xml', snapshot,
                    'resources/fiscal/scenarios/dps-local-2026.10.01-6/presential-identity.json',
                    f'--bundle-manifest={BUNDLE}', f'--candidate-output={candidate}', f'--report={report_path}'])
            assert report['reason'] == 'RTC_DPS_OFFICIAL_SERIE_PATTERN_CONFLICT', report
            assert len(report['schema_validation']['errors']) == 1 and report['production_ready'] is False
            assert report['decision_hash'] == value['decision_hash']
            assert hashlib.sha256((ROOT / candidate).read_bytes()).hexdigest() == report['schema_validation']['xml_sha256']
            print(name, value['decision_hash'])
    else:
        status, reason = negative[name]
        assert value['status'] == status and value['reason_codes'] == [reason], (name, value)
    save(f'resources/fiscal/expected/consumption-integrated-{VERSION}/{full_name}.json',
         {key: value[key] for key in ['status', 'reason_codes', 'missing_facts', 'decision_hash']})
corpus = execute(['fiscal:corpus:run', f'resources/fiscal/scenarios/consumption-integrated-{VERSION}',
    f'--expected=resources/fiscal/expected/consumption-integrated-{VERSION}', f'--bundle-manifest={BUNDLE}'])
assert corpus['valid'], corpus
save('storage/app/phase4b-cooperative-network-transfer-corpus-20261002-10.json', corpus)
gate = execute(['fiscal:rtc:production-assess', f'--bundle-manifest={BUNDLE}', f'--controls=resources/fiscal/production/phase4b-controls-{VERSION}.json'])
assert gate['ready'] is False and gate['eligible_for_local_validation'] is True
assert gate['official_sources_checked'] == gate['official_source_hashes_valid'] == 20
save('storage/app/phase4b-production-assessment-20261002-10.json', gate)
print('Verified21 integrated cases/4 snapshots/4 XML candidates; blockers:', len(gate['blockers']))
