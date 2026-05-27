# ✅ Laravel ToDo

> Laravelで構築したタスク管理Webアプリケーション

![PHP](https://img.shields.io/badge/PHP-777BB4?style=flat-square&logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-FF2D20?style=flat-square&logo=laravel&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=flat-square&logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=flat-square&logo=javascript&logoColor=black)

---

## 📌 概要

ユーザーが自分のタスクを登録・管理できるWebアプリです。  
アカウント登録・ログインにより、各ユーザーのタスクをセキュアに管理します。  
タスクの追加・編集・削除に加え、バリデーションや画面遷移の利便性向上も実装しています。

---

## 🛠️ 技術スタック

| カテゴリ | 使用技術 |
|---|---|
| バックエンド | PHP / Laravel |
| フロントエンド | HTML / CSS / JavaScript |
| データベース | MySQL |

---

## ✨ 主な機能

- **ユーザー認証** ― 新規登録・ログイン・ログアウト
- **タスク管理（CRUD）** ― 一覧・詳細・作成・編集・削除
- **フォームバリデーション** ― 未入力時のJavaScriptアラート表示
- **UI設計** ― CSSによるレイアウト構築・画面遷移の最適化

---

## 🔧 設計のポイント

- **MVC アーキテクチャ** ― LaravelのRouting / Controller / Model / View を活用した実装
- **認証** ― Laravel標準の認証機能でユーザーごとのデータを分離管理
- **フロント連携** ― JavaScriptでバリデーションを追加しUXを向上

---

## 📸 スクリーンショット

| 画面 | |
|---|---|
| 新規登録 | ![新規登録ページ](imags/sinup-page.png) |
| 新規登録（アラート表示） | ![アラート](imags/sinup-page_alert.png) |
| ログイン | ![ログインページ](imags/login-page.png) |
| タスク一覧 | ![タスク一覧](imags/tasklist-page.png) |
| タスク詳細 | ![タスク詳細](imags/taskContent-page.png) |
| タスク作成 | ![タスク作成](imags/taskmake-page.png) |
| タスク編集 | ![タスク編集](imags/taskedit-page.png) |
