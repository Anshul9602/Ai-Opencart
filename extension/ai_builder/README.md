# AI Website Builder Assistant — OpenCart 4 Extension

AI-powered chat assistant that lets administrators manage their entire OpenCart store through natural language.

## Features

- **Full-screen AI Chat** — ChatGPT-style interface inside OpenCart Admin
- **Banner Management** — List, replace, add slides with image upload & cache clear
- **Product Management** — Create, update, search, CSV import, bulk price updates
- **Category & Manufacturer** — Create, search, update, delete
- **Orders & Customers** — Today's summary, search, status updates
- **Coupons** — Create discount coupons via conversation
- **Information Pages** — Edit About Us, Privacy Policy, etc.
- **Settings** — Update logo, favicon, store settings
- **SEO & Images** — Search media, find products without images
- **Safety** — Confirmation for destructive operations
- **Audit Log** — Every AI action logged with user, timing, affected records
- **Dark/Light Mode** — Modern responsive UI

## Requirements

- OpenCart 4.x
- PHP 8.0+
- OpenAI API key
- cURL extension
- GD extension (for image processing)

## Installation

### Method 1: Manual Copy (Recommended for development)

1. Copy the entire `ai_builder` folder to your OpenCart `extension/` directory:

```
your-opencart/
└── extension/
    └── ai_builder/    ← copy this folder here
```

2. Log in to OpenCart Admin

3. Go to **Extensions → Installer**
   - If using zip: create `ai_builder.ocmod.zip` containing the `ai_builder` folder contents + `install.json`
   - Upload and install

4. Go to **Extensions → Extensions → Other**
   - Find **AI Website Builder**
   - Click **Install** (green +)
   - Click **Edit** (blue pencil) to configure

5. Add your **OpenAI API Key** and save

6. Go to **Extensions → Modifications**
   - Click **Refresh** to apply the admin menu modification

7. Click **AI Website Builder** in the admin sidebar (robot icon)

### Method 2: Zip Install

```bash
cd extension/ai_builder
# Create zip with install.json at root level
zip -r ../ai_builder.ocmod.zip . -x "*.git*"
```

Upload `ai_builder.ocmod.zip` via **Extensions → Installer**.

## Configuration

| Setting | Description |
|---------|-------------|
| Status | Enable/disable extension |
| OpenAI API Key | Your sk-... API key |
| Model | gpt-4o-mini (recommended), gpt-4o, gpt-4-turbo |
| Temperature | 0.0-1.0 (lower = more focused) |
| Confirm Destructive | Require Yes/No for bulk deletes |

## Usage Examples

```
Change homepage banner
Add new product
Show today's orders
Find customer Rahul
Create coupon SUMMER20 15% off
Increase all prices by 5%
Find all products without image
Update About Us page
Upload logo
```

## Architecture

```
extension/ai_builder/
├── admin/                    # Admin MVC
│   ├── controller/other/     # Main controller + API endpoints
│   ├── model/other/          # DB: sessions, messages, audit
│   ├── view/                 # Chat UI (twig, css, js)
│   └── language/             # Translations
├── system/library/           # Business logic (PSR-4)
│   ├── Ai/                   # OpenAI client
│   ├── Chat/                 # Orchestrator, ActionExecutor
│   ├── Services/             # Product, Banner, Order, etc.
│   ├── Prompt/               # System prompts
│   └── Utils/                # Image, CSV, Cache utilities
├── ocmod/                    # Admin menu modification
├── install.json              # Extension metadata
├── install.xml               # OCMOD modification (menu)
└── composer.json             # PSR-4 autoload
```

## API Endpoints (AJAX)

| Route | Method | Description |
|-------|--------|-------------|
| `.../ai_builder.chat` | GET | Full-screen chat UI |
| `.../ai_builder.send` | POST | Send chat message |
| `.../ai_builder.upload` | POST | Upload file (image/csv) |
| `.../ai_builder.confirm` | POST | Confirm destructive action |
| `.../ai_builder.sessions` | GET | List chat sessions |
| `.../ai_builder.history` | GET | Load session messages |
| `.../ai_builder.csvTemplate` | GET | Download CSV template |

## Database Tables

- `oc_ai_builder_session` — Chat sessions
- `oc_ai_builder_message` — Chat messages
- `oc_ai_builder_audit` — Action audit log

## Permissions

- `access` — View AI chat
- `modify` — Send messages, execute actions

Auto-granted to Administrator group on install.

## Future Roadmap

- Voice commands
- AI image generation
- AI product descriptions
- Multi-language support
- Multi-store support
- Plugin architecture for custom AI skills

## License

MIT
