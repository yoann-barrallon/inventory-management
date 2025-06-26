# 🛠️ Tech Stack

## ⚙️ Backend

**Laravel 12**

- Robust PHP framework for modern backend development.
- Used with the official React Starter Kit.
- Built-in features:
    - Authentication via Jetstream (with Inertia.js)
    - Middleware, routing, queues, events, etc.
    - Eloquent ORM for database management.

## 💡 Full-stack Bridge

**Inertia.js**

- Bridge between Laravel and React.
- Enables building a single-page application (SPA) without a separate REST API.
- Smooth communication between backend views and frontend components.

## 🎨 Frontend

**React 19**

- Used to build dynamic and interactive user interfaces.
- Acts as the main frontend via Inertia.js.

**Tailwind CSS**

- Utility-first CSS framework for rapid and responsive styling.
- Integrated with Laravel’s build pipeline (Mix/Vite).

**shadcn/ui**

- UI component library based on Radix UI and Tailwind CSS.
- Helps build elegant, accessible interfaces quickly.

## 📦 Build & Tools

- **Vite** (or Laravel Mix depending on config): fast bundler for frontend assets.
- **Composer & NPM**: package managers for PHP and JS.
- **ESLint & Prettier**: linting and code formatting for the frontend.

## ✅ Testing

- **PestPHP** for backend testing.

---

> This project follows a **monolithic full-stack** approach with a clear separation of concerns via Inertia.js. The stack is designed for scalability, fast development, and a great developer experience (DX).
