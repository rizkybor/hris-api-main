# Indonesian administrative regions

Compact CSV copies of the Indonesian region codes, imported into the
`regions_provinces`, `regions_regencies`, `regions_districts` and
`regions_villages` tables with:

```
php artisan regions:import
```

The import is idempotent (upsert on `code`) and runs in chunks.

| File            | Columns                        | Rows   |
|-----------------|--------------------------------|--------|
| `provinces.csv` | `code,name`                    | 38     |
| `regencies.csv` | `code,province_code,name`      | 514    |
| `districts.csv` | `code,regency_code,name`       | 7,285  |
| `villages.csv`  | `code,district_code,name`      | 83,762 |

Codes are the official dotted codes (`31`, `31.71`, `31.71.01`,
`31.71.01.1001`); a child's code starts with its parent's code.

## Source and licence

- Source: [cahyadsn/wilayah](https://github.com/cahyadsn/wilayah), file
  `db/wilayah.sql` (last edit 2026-02-13), "Data Kode Wilayah sesuai
  Kepmendagri No 300.2.2-2138 Tahun 2025" (38 provinces).
- Licence: MIT License, copyright (c) 2025 cahya dsn. The original notice:

  > This program is free software; you can redistribute it and/or modify it
  > under the terms of the MIT License. THE SOFTWARE IS PROVIDED "AS IS",
  > WITHOUT WARRANTY OF ANY KIND.

- Downloaded 2026-11-10 and converted to CSV (one file per level, the parent
  code derived from the dotted code). No names were changed.

To refresh: download `db/wilayah.sql` from the repository again, regenerate
the four CSVs in the same format, and run `php artisan regions:import`.
