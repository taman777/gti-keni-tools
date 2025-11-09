# GTI 賢威-SYN 管理ツール

WordPress テーマ「賢威8」から「SYN（SYNオウンド）」への移行を支援するための管理ツールです。  
PV統合や TOC（目次）自動挿入など、移行時に役立つ機能をまとめています。

---

## 🧰 主な機能

### ✅ PV統合ツール
- 賢威テーマで保持していた PV カウントデータを SYN に統合。
- Dry-run（件数確認）＋バッチ処理対応。

### ✅ SYN TOC（目次）自動挿入機能
- 記事本文に `[synx_toc]` が無い場合に自動で TOC を生成。
- 「賢威-SYNツール」メニュー配下から以下を設定可能：
  - 自動表示 ON / OFF  
  - 挿入位置（タイトル下 or 最初のH2前）  
  - 見出し深さ（h2〜h4まで）

---

## 💡 使い方

1. プラグインをダウンロードし、`/wp-content/plugins/gti-keni-tools/` にアップロード。
2. WordPress 管理画面 → **プラグイン → 有効化**
3. メニュー「**賢威-SYNツール**」から各機能を利用できます。

---

## 🧩 開発構成
gti-keni-tools/
├── gti-keni-tools.php # メインプラグインファイル
├── inc/
│ ├── keni-tools-core.php # 管理メニュー・モジュール基盤
│ └── tools/
│ ├── pv-integrator.php # PV統合モジュール（例）
│ └── toc-manager.php # TOC自動挿入モジュール（今回追加）
└── vendor/
└── yahnis-elsts/plugin-update-checker/ # GitHub自動更新


---

## 🪄 アップデート履歴

### 1.1.0（2025-11-09）
- SYN テーマ用 TOC（目次）自動挿入機能を追加
- 「賢威-SYNツール」配下に「SYN目次設定」メニューを追加

### 1.0.0（2025-10-31）
- 初回リリース

---

## 🧑‍💻 開発者

- **株式会社ジーティーアイ (GTI)**
- Website: [https://gti.jp/](https://gti.jp/)
- GitHub: [taman777/gti-keni-tools](https://github.com/taman777/gti-keni-tools)

---

## 🪶 ライセンス

This plugin is released under the [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html).


