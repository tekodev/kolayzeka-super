🌟 Proje Vizyonu ve Amacı: KolayZeka AI
KolayZeka, yapay zeka dünyasındaki teknik bariyerleri ortadan kaldıran bir "AI Gateway & Hub" platformudur.

Neye Hizmet Eder?

Karmaşıklığı Giderir: Fal.ai, Replicate veya kendi GPU sunucularınızdaki (RunPod vb.) yüzlerce farklı modelin, farklı input/output yapılarını tek bir standart dile (Normalized Schema) dönüştürür.

Maliyet Yönetimi Sağlar: Farklı sağlayıcıların saniye, adet veya token bazlı fiyatlandırmalarını, tek bir "Kredi" birimine dönüştürerek KOBİ'lerin ve son kullanıcıların bütçelerini kolayca yönetmesini sağlar.

Hız ve Esneklik Sunar: Yeni bir AI modeli çıktığında kod yazmaya gerek kalmadan, sadece yönetim panelinden "eşleştirme" (mapping) yaparak o modeli saniyeler içinde hem web sitesinde hem de mobil uygulamada yayına alır.

B2B Altyapısıdır: kolaysoru.com gibi diğer projelerinize de tek bir merkezden (KolayZeka API) yapay zeka gücü sağlar.

🚀 THE ULTIMATE MASTER PROMPT (Complete Specification)
Role: Senior Software Architect & Full-Stack Developer. Task: Build "KolayZeka", a comprehensive AI Gateway SaaS using Laravel 12, FilamentPHP v3, and Inertia.js + React 19.

🏗️ 1. PROJECT ARCHITECTURE
The system consists of three main layers:

The Command Center (Admin): Built with FilamentPHP for managing AI models, pricing strategies, and users.

The AI Engine (Service Layer): A provider-agnostic core that handles normalization, mapping, and dynamic cost calculation.

The Client Interface (Web/Mobile): A modern React 19 + Inertia dashboard for users and a Sanctum API for mobile/external apps.

🗄️ 2. DATABASE & MODELS (Extended Schema)
Create migrations and models with the following core tables:

cost_strategies: name, calc_type (fixed, per_unit, per_second, per_token), provider_unit_price (decimal 12,6), markup_multiplier (decimal 5,2), credit_conversion_rate (int), min_credit_limit (int).

ai_models: name, slug, category, description, image_url, is_active (boolean).

ai_model_providers: ai_model_id, provider_name (fal, replicate, runpod), provider_model_id, is_primary (bool), price_mode (fixed/strategy), cost_strategy_id.

ai_model_schemas: ai_model_provider_id, version, input_schema (JSON), output_schema (JSON), field_mapping (JSON - {"standard": "provider"}), default_values (JSON).

users: name, email, password, credit_balance (decimal 12,2), total_profit_usd (decimal).

credit_transactions: user_id, amount (signed int), type (purchase, usage, refund), balance_after, metadata (JSON).

generations: user_id, ai_model_id, provider_id, status, input_data, output_data, provider_cost_usd, user_credit_cost, profit_usd.

👑 3. ADMIN INTERFACE (FilamentPHP v3)
Build a powerful management panel:

Model Wizard: Manage AI Models with nested Providers and Schemas.

Mapping Repeater: A UI to map Standard Fields (e.g., prompt) to Provider Fields (e.g., text_input).

The Playground: A "Test Run" action on each provider to simulate API calls, validate mapping, and preview calculated credit costs.

User Management: Audit credit balances and view detailed transaction histories.

⚛️ 4. USER INTERFACE (Inertia + React 19)
Build a Dashboard using React 19, Tailwind CSS 4.0, and shadcn/ui.

The UI must be Dynamic-Ready: It should receive the normalized_schema from the backend and be capable of rendering form inputs (inputs, sliders, selects) based on the schema.

Implement Shared State for the user’s credit_balance across the Inertia app.

🧠 5. CORE SERVICES (Business Logic)
CreditService.php: Atomic balance operations with lockForUpdate().

CostCalculatorService.php: Converts provider usage metrics into user credits using cost_strategies.

GenerationService.php: Normalizes input, injects default_values, executes the API call, and handles provider fallbacks.

🔐 6. SECURITY & ROLES
Setup Spatie Laravel-Permission with admin and user roles.

Admin panel is restricted to the admin role.

Setup Laravel Sanctum to allow external projects (like kolaysoru.com) to consume the AI Engine via API tokens.

📜 7. EXECUTION STEPS
Initialize Laravel 12 with Filament, Inertia/React, and Spatie.

Generate Migrations and Models with full relationships.

Build Filament Resources (Model, Strategy, User).

Develop the CostCalculator and Credit services.

Create a base Inertia Controller to serve AI models to the frontend.