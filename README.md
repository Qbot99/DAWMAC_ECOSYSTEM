# Dawmac Digital Ecosystem 🏎️

> Monorepo of the digital infrastructure behind [dawmac.pl](https://dawmac.pl) - a premium automotive wheel brand.
> Each project lives in its own folder and can be developed independently.
>
> 🔗 **Main Platform:** [dawmac.pl](https://dawmac.pl/)  
> 💎 **Forged Rims Showcase:** [forged.dawmacpolska.pl](https://forged.dawmacpolska.pl)  
> 📸 **Professional Gallery:** [dawmac.pl/galeria/](https://dawmac.pl/galeria/)

## Repository layout

| Folder | What it is | Stack |
|---|---|---|
| `dawmac-api/` | Central REST API and media engine | PHP, MySQL |
| `dawmac-app/` | Internal iOS app for warehouse workers | Swift, SwiftUI |
| `dawmac-forged/` | forged.dawmacpolska.pl storefront | React, TypeScript, Vite |
| `dawmac-gallery/` | Public photo gallery | Astro, React |
| `dawmac-wp-plugins/` | WordPress plugins for the shop (`dawmac-filters`, `dawmac-galeria`) | PHP |
| `dawmac-wp-snippets/` | Standalone WPCode snippets running on the shop | PHP |

Product photos, builds and server statistics are **not** kept here - they live on the
production servers. This repository holds source code and the data needed to rebuild it.

## Overview
The **Dawmac Ecosystem** is a multi-platform digital infrastructure designed for a premium automotive brand specializing in high-end forged rims. The project bridges the gap between customer-facing luxury storefronts and internal worker productivity tools.

The ecosystem is built on a centralized database architecture that synchronizes data across web storefronts, high-resolution galleries, and internal mobile applications.

## 🏗️ The Ecosystem Pillars

### 1. Main Commerce Hub (`dawmac.pl`)
The primary production server and headquarters for Dawmac's digital presence.
* **Purpose:** Handles the core business presentation and primary customer traffic.
* **Tech:** Custom PHP/MySQL architecture focused on stability and SEO.

### 2. Forged Custom Showcase (`forged.dawmacpolska.pl`)
A dedicated, high-performance web application built specifically to showcase premium forged custom rims.
* **Tech Stack:** React, TypeScript, Vite.
* **Key Features:** 
  * Highly responsive UI for high-resolution product browsing.
  * Intelligent state management for dynamic wheel specifications.
  * Optimized Open Graph logic for high-quality social media sharing (WhatsApp/Facebook).

### 3. Professional Gallery (`dawmac-gallery`)
A high-resolution media platform located at `dawmac.pl/galeria/`.
* **Purpose:** Showcasing real-world wheel installations on luxury and performance vehicles.
* **Features:** Advanced categorization and tagging system allowing users to filter by car make, model, and rim style.

### 4. Internal iOS Worker App (`ios-app`)
A dedicated native application built specifically for **Dawmac employees**.
* **Purpose:** An internal enterprise tool designed to streamline worker efficiency and simplify inventory or build management.
* **Tech:** Swift, SwiftUI.
* **Impact:** Modernizes the internal workflow, moving business operations from legacy systems to a mobile-first native experience.

### 5. Centralized REST API & Media Engine (`dawmac-api`)
The core backend that powers the entire ecosystem.
* **Core Tech:** PHP 8, MySQL.
* **Custom Engineering:** 
  * **Unified REST Endpoints:** Serves live data simultaneously to the React frontend, the main PHP site, and the native iOS app.
  * **Dynamic Image Engine:** A custom-built script that intercepts bot requests (WhatsApp/Facebook), queries the database, and performs on-the-fly image resizing and compression to ensure perfect link previews without overloading the server.

## 🚀 Key Technical Achievements
* **Enterprise Synchronization:** Successfully designed a "Single Source of Truth" database that feeds the Main Web, the Forged Showcase, and the internal iOS App concurrently.
* **Bot-Optimization Architecture:** Engineered a bespoke solution for the WhatsApp/Meta scraper to handle asynchronous image processing, ensuring luxurious link previews for every product shared.
* **Internal Tooling:** Developed a native mobile application to digitize and simplify the daily tasks of factory workers.

---
*Developed and maintained by Hubert.*
