# GTI 賢威-SYN 管理ツール

WordPress テーマ **「賢威8」** から **「SYN（SYNオウンド）」** への移行を支援するための管理ツールです。  
PV統合、TOC（目次）自動挿入、アイキャッチ表示移行など、移行時の作業を簡略化するための機能をまとめています。

---

## 🧰 主な機能

### ✅ PV統合ツール
- 賢威テーマで保持していた PV カウントデータを SYN に統合
- Dry-run（件数確認）＋バッチ処理による安全な移行

---

### ✅ SYN TOC（目次）自動挿入機能
- 記事本文に `[synx_toc]` が無い場合、自動的に TOC を生成
- 管理画面「賢威-SYNツール」配下に設定画面を用意
- 設定可能項目
  - TOC の自動表示 ON / OFF  
  - 挿入位置（タイトル直下 / 最初の H2 の前）  
  - 見出しレベル（H2〜H4）

---

### ✅ アイキャッチポリシー移行ツール（賢威 → SYN）【v1.2.0】
賢威8 と SYN ではアイキャッチ表示仕様が異なるため、  
過去記事のアイキャッチ表示状態を正しく移行するための一括変換ツールです。

#### ● 主な動作
- 賢威の **個別設定 `keni_thumbnail_disp_post`**  
- 賢威の **全体設定 `keni_thumbnail_disp`**  
を読み取り、SYN の `_synx_eyecatch` に変換  
（※ `_synx_eyecatch = 1` が「非表示」）

#### ● Dry-run（書き込みなし）
- 対象件数・更新件数・スキップ件数を表示  
- 記事ごとの変換結果を一覧表示  
- 投稿状態・日時・賢威設定（個別/全体）も確認可能  

#### ● 実行モード（書き込みあり）
- `_synx_eyecatch = 1` を安全にセット  
- すでに `_synx_eyecatch` が存在する記事はスキップ  

#### ● 変換ルール（抜粋）
| 賢威 全体 | 賢威 個別 | SYN 設定 |
|----------|-----------|-----------|
| 表示 | デフォルト / 表示 | そのまま（未設定） |
| 表示 | 非表示 | 非表示に変換（1） |
| 非表示 | デフォルト / 非表示 | 非表示に変換（1） |
| 非表示 | 表示 | そのまま（未設定） |

---

## 💡 使い方

1. プラグインをダウンロードし、`/wp-content/plugins/gti-keni-tools/` にアップロード  
2. WordPress 管理画面 → **プラグイン → 有効化**  
3. メニュー **「賢威-SYNツール」** から各機能を利用できます  
   - PV統合  
   - TOC設定  
   - アイキャッチ移行ツール（Dry-run / 実行）

---

## 🧩 開発構成
```text
gti-keni-tools/
├── gti-keni-tools.php                 # メインプラグインファイル
├── inc/
│   ├── keni-tools-core.php            # 管理メニュー・モジュール基盤
│   └── tools/
│       ├── pv-merge.php          # PV統合モジュール
│       ├── toc-manager.php            # TOC自動挿入モジュール
│       └── eyecatch-policy-migrator.php  # アイキャッチ移行ツール（v1.2.0）
└── vendor/
    └── yahnis-elsts/plugin-update-checker/  # GitHub自動更新
```

---

## 🪄 アップデート履歴（Changelog）

### **1.2.0（2025-11-16）**
**賢威 → SYN のアイキャッチポリシー移行ツールを追加**
- 賢威の個別設定・全体設定を読み取り、SYN の `_synx_eyecatch` に変換  
- Dry-run で結果一覧を確認可能  
- 実行モードで `_synx_eyecatch = 1`（非表示）を安全にセット  
- `_synx_eyecatch` 設定済み記事はスキップ  
- 処理結果を管理画面の表形式で確認可能

### **1.1.0（2025-11-09）**
- SYN テーマ向け TOC（目次）自動挿入を新規追加  
- 管理メニュー「SYN目次設定」を追加

### **1.0.0（2025-10-31）**
- 初回リリース
- PV統合ツールを実装
- 管理メニュー「賢威-SYNツール」を追加

---

## 🧑‍💻 開発者

**株式会社ジーティーアイ (GTI)**  
Website: https://gti.co.jp/  
GitHub: https://github.com/taman777/gti-keni-tools

---

## 🪶 ライセンス

This plugin is released under the  
**GPLv2 or later**  
https://www.gnu.org/licenses/gpl-2.0.html

---

© 2025 GTI Inc. All rights reserved.
