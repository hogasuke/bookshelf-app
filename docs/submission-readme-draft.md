# BookShelf 書籍レビューアプリ（提出用README草案・未完成）

書籍レビューアプリのCOACHTECH模擬案件。書籍一覧・詳細を実装済みですが、書籍の登録更新削除・公開APIなどは未実装で、この草案の存在は提出可能の証拠ではありません。

## 概要

既存実装：Fortify導入、認証用Blade、基本テーブル/モデル、ER図。今回、ReviewからUser/Book、FavoriteからBookの参照を追加し、隔離DBで確認しました。books作成者IDとその削除方針は設計承認待ちです。

## 使用技術

Laravel 10 / PHP 8.5 / MySQL 8.4 / Laravel Sail / Fortify / Vite / Tailwind CSS。テストはPHPUnit 10。Sanctumは依存にありますが、応用API認証が完成したという意味ではありません。

## 作成者

大沢 敬一朗。AI支援範囲と申告方法は学校への確認待ちです。

## ER図

[編集原本](db/er.drawio) / [画像](db/er.png)。要確認の設計は未確定です。

## 環境構築手順（再現試験待ち）

1. Dockerを起動します。PHP 8.5とComposer、Node.js/npmを用意します。導入が必要な場合は本人の承認を得ます。
2. 既存リポジトリを取得し、プロジェクト直下で `composer install` を実行します（lock固定、updateしない）。
3. `.env.example` を `.env` にコピーし、ローカル用DB設定と空いているAPP_PORT等を設定します。秘密はREADMEへ書きません。
4. `vendor/bin/sail up -d`、`vendor/bin/sail artisan key:generate`、`vendor/bin/sail artisan migrate` を実行します。既存DBを消すfreshは使いません。
5. `vendor/bin/sail npm ci`、`vendor/bin/sail npm run build` を実行します。
6. Seeder未完成のため、採点用指定データを投入できる状態ではありません。承認後のSeeder完成時に手順を更新します。
7. テストは専用testing DBと接続設定を確認してから `vendor/bin/sail artisan test`。正式coverageは `vendor/bin/sail artisan test --coverage`、整形確認は `vendor/bin/sail bin pint --test`。現在Sail/MySQL再現は未実施です。

## 開発環境URL

標準設定は http://localhost 。APP_PORTを変更した場合はそのポートを使用します。今回のブラウザ実画面確認は未実施です。

## APIエンドポイント

以下は課題指定の実装予定で、現時点では未実装です。

|メソッド|パス|概要|
|---|---|---|
|GET|/api/v1/books|書籍一覧（検索・絞込・ページ分割）|
|GET|/api/v1/books/{book}|書籍詳細|
|POST|/api/v1/books|登録|
|PUT|/api/v1/books/{book}|更新|
|DELETE|/api/v1/books/{book}|削除|

## 現在の検証

2026-10-28：`php vendor/bin/phpunit --no-progress` が25 tests / 278 assertionsで成功。`php vendor/bin/pint --test` と `git diff --check` も成功。リレーションテストはSQLite :memory:でマイグレーションし、既存MySQLを使用しません。基本全カテゴリのテスト完成やMySQL互換性の証明ではありません。カバレッジドライバがなく未測定。コーチ質問は作業場の `projects/coachtech/submission/coach-questions-20261028.md` にあります。

提出前に、実装状況・設計承認・AI利用条件・本人必須工程・再現手順を確認してから、この草案をルートREADMEへ反映します。

2026-10-28追加：ジャンル一覧/詳細・お気に入り一覧・ランキングを実装。書籍登録フォーム表示、BookPolicy、BookWriter更新/削除コア、SocialToggles、5モデルFactoryも追加。書込HTTPは未接続、作成者DB列は承認待ち。Bladeコンパイル成功。正式ビルドはMac用Rollup依存不足で未成功。

最終追記：ReviewPolicy/認証必須編集画面・ReviewWriterコア、固定UserSeeder5件/GenreSeeder10件を追加検証。全体SeederとHTTP書込は未完成。

最終追記（16:00 JST）：基本6Seeder・呼出順・全体transaction、Genre/Book/Review作成DBコアを実装。成功系はテスト内作成者列fixtureで検証、実アプリschemaでは安全停止を確認。実DBの正式migrationとHTTP/APIの設計承認は未完了。境界の最新版は作業場submission/implementation-boundaries-20261028.md。
