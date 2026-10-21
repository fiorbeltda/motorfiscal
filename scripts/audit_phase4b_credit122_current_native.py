"""Independent integrity audit of550 current native captures;no functional Phase4B closure."""
from datetime import datetime, timezone
from pathlib import Path
from phase4b_credit122_capture_support import R, E, read, sha, save, canonical, compare_compatibility_result, core_inventory


def verify():
    checks = 0

    def check(condition, label):
        nonlocal checks
        assert condition, label
        checks += 1

    prep = read(E/'credit122-expanded-preparation-validation.json')
    credit_root, expanded_root = E/'credit-cli-v2', E/'expanded-cli-v1'
    credit = read(credit_root/'credit122-cli-validation.json')
    expanded = read(expanded_root/'credit122-expanded-cli-validation.json')
    check(credit['native_decisions'] == credit['exact_replays'] == 180, 'credit180')
    check(expanded['native_decisions'] == expanded['exact_replays'] == 370, 'expanded370')
    check(credit['runtime_hashes'] == expanded['runtime_hashes'] == prep['runtime_hashes'], 'same94 runtime')
    check(len(prep['runtime_hashes']) == 94, 'runtime count')
    for path, digest in prep['runtime_hashes'].items():
        check(sha(R/path) == digest, 'runtime:'+path)
    core = core_inventory()
    for root in [credit_root, expanded_root]:
        frozen = read(root/'preflight.json')['input_hashes']
        for path, digest in frozen.items():
            check(sha(path) == digest, 'frozen:'+path)
        roots = [R/folder for folder in ['app', 'tests', 'resources/fiscal']]
        scoped_frozen = {path for path in frozen if any(Path(path).is_relative_to(scope) for scope in roots)}
        scoped_current = {path for path in core if any(Path(path).is_relative_to(scope) for scope in roots)}
        # The124 price fixtures,2 manifests and one test were installed after credit180.
        expected_additions = 127 if root == credit_root else 0
        check(scoped_frozen <= scoped_current and len(scoped_current-scoped_frozen) == expected_additions,
              'declared membership additions:'+str(root))

    snapshots = set()

    def native(root, snapshot_name, digest, scenario, runner):
        path = root/snapshot_name
        check(path not in snapshots, 'unique snapshot')
        snapshots.add(path)
        check(sha(path) == digest, 'snapshot hash')
        value = read(path)
        check(canonical(value['input']) == canonical(scenario), 'typed native input')
        label = snapshot_name.removesuffix('-snapshot.json')
        actual = read(root/(label+'.stdout'))
        check(canonical(value['result']) == canonical(actual), 'typed native result')
        for suffix in ['', '-replay']:
            receipt = read(root/(label+suffix+'-execution.json'))
            check(receipt['exit_code'] == receipt['expected_exit_code'], 'native/replay terminal exit')
            for stream in ['stdout', 'stderr']:
                check(sha(root/(label+suffix+'.'+stream)) == receipt[stream+'_sha256'], 'raw captured stream hash')
        replay = read(root/(label+'-replay.stdout'))
        check(replay['replay_matches'] is True, 'own exact replay')
        if runner == 'integrated':
            check(canonical(replay['result']) == canonical(actual), 'typed integrated replay')
        return value

    check(len(credit['cases']) == 90, 'all90 credit cases')
    for row in credit['cases']:
        scenario_path = credit_root/row['scenario']
        check(sha(scenario_path) == row['scenario_sha256'], 'credit scenario hash')
        for runner in ['rtc', 'integrated']:
            source = row[runner]
            native(credit_root, source['snapshot'], source['snapshot_sha256'], read(scenario_path), runner)

    check(len(expanded['cases']) == 124, 'all124 price cases')
    for row in expanded['cases']:
        check(sha(R/row['path']) == row['scenario_sha256'], 'price scenario hash')
        for runner in ['rtc', 'integrated']:
            native(expanded_root, row[runner+'_snapshot'], row[runner+'_snapshot_sha256'], read(R/row['path']), runner)

    sources = read(E/'credit122-compatibility119-source-index-v1.json')['cases']
    check(len(expanded['compatibility_cases']) == len(sources) == 61, 'all61 compatibility cases')
    for row, original_row in zip(expanded['compatibility_cases'], sources, strict=True):
        check((row['family'], row['name']) == (original_row['family'], original_row['name']), 'compatibility identity')
        scenario_path = expanded_root/row['scenario']
        check(sha(scenario_path) == row['scenario_sha256'], 'compatibility scenario hash')
        for runner in ['rtc', 'integrated']:
            item = row[runner]
            value = native(expanded_root, item['snapshot'], item['snapshot_sha256'], read(scenario_path), runner)
            source = original_row['runners'][runner]
            check(sha(source['snapshot']) == source['snapshot_sha256'] == item['source_snapshot_sha256'], 'accepted119 source identity')
            old = read(source['snapshot'])
            changes = compare_compatibility_result(old['result'], value['result'], runner, old_input=old['input'], new_input=value['input'])
            check(changes == item['declared_transport_metadata_changes'], 'only declared metadata;fiscal assessment preserved')
    check(len(snapshots) == 550, 'all550 current native snapshots')
    return {'created_at': datetime.now(timezone.utc).isoformat(), 'status': 'CREDIT122_CURRENT_NATIVE_INTEGRITY_VERIFIED',
            'audit_checks': checks, 'native_decisions': 550, 'exact_replays': 550,
            'runtime_hashes': prep['runtime_hashes'], 'bundle': prep['bundle'], 'bundle_sha256': prep['bundle_sha256'],
            'credit_corpus_sha256': sha(credit_root/'credit122-cli-validation.json'),
            'expanded_corpus_sha256': sha(expanded_root/'credit122-expanded-cli-validation.json'),
            'full_suite_complete': False, 'historical_current_acceptance_separate': True,
            'phase4b_complete': False, 'production_ready': False}


if __name__ == '__main__':
    report = verify()
    save(E/'credit122-current-native-audit-v1.json', report)
    print({key: report[key] for key in ['status', 'audit_checks', 'native_decisions', 'exact_replays']}, flush=True)
