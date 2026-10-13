<?php
declare(strict_types=1);
require __DIR__ . '/../src/Sync.php';

$fail = 0;
function eq($a, $b, string $msg): void { global $fail; if ($a !== $b) { $fail++; echo "FAIL $msg: ", var_export($a, true), " !== ", var_export($b, true), "\n"; } }

foreach ([
    '+7 999 123-45-67' => '+79991234567', '8 (999) 123 45 67' => '+79991234567',
    '79991234567' => '+79991234567', '9005551212' => '+79005551212', '+7-926-123-45-67' => '+79261234567',
    '123' => null, '' => null, 'не указан' => null, '+7 999' => null, '+1 202 555 0100' => null,
] as $in => $out) eq(Sync::normalizePhone((string)$in), $out, "phone '$in'");

$r = Sync::process([
    ['id'=>1,'name'=>'A','phone'=>'+7 999 123-45-67','email'=>'a@x','amount'=>1000,'created_at'=>'2026-10-01T10:00:00Z'],
    ['id'=>2,'name'=>'B','phone'=>'8 (999) 123 45 67','email'=>'','amount'=>5000,'created_at'=>'2026-10-02T11:00:00Z'],
    ['id'=>3,'name'=>'C','phone'=>'8-916-000-00-01','email'=>'c@x','amount'=>200,'created_at'=>'2026-10-01T12:00:00Z'],
    ['id'=>4,'name'=>'D','phone'=>'+7 (916) 000 00 01','email'=>'d@x','amount'=>200,'created_at'=>'2026-10-05T12:00:00Z'],
    ['id'=>5,'name'=>'E','phone'=>'123','email'=>'','amount'=>1,'created_at'=>''],
]);
eq(array_column($r['clients'], 'name'), ['B', 'D'], 'dedupe winners');
eq($r['stats'], ['received'=>5,'unique'=>2,'duplicates_merged'=>2,'invalid_dropped'=>1], 'stats');
eq(Sync::process(array_reverse([['id'=>1,'phone'=>'9991234567','amount'=>1,'created_at'=>'2026-01-01'],['id'=>2,'phone'=>'89991234567','amount'=>1,'created_at'=>'2026-01-02']]))['clients'][0]['amount'], 1, 'order independent');

echo $fail ? "$fail failed\n" : "all ok\n";
exit($fail ? 1 : 0);
