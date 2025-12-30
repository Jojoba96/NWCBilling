# NWC Customer Dashboard - Setup Guide

## 🚀 Quick Setup (Recommended)

### Option 1: Automatic Setup (Windows)

1. **Navigate to project root:**
   ```
   cd C:\xampp\htdocs\NWCBilling\
   ```

2. **Run the build script:**
   ```
   build-and-deploy.bat
   ```

   This will:
   - ✅ Install dependencies
   - ✅ Build the React app
   - ✅ Prepare everything for deployment

3. **Open in browser:**
   ```
   http://localhost/NWCBilling/customer-dashboard.php
   ```

---

### Option 2: Manual Setup (All Platforms)

1. **Navigate to frontend folder:**
   ```bash
   cd C:\xampp\htdocs\NWCBilling\frontend
   ```

2. **Install dependencies (first time only):**
   ```bash
   npm install
   ```

3. **Build the React app:**
   ```bash
   npm run build
   ```

4. **Open in browser:**
   ```
   http://localhost/NWCBilling/customer-dashboard.php
   ```

---

## 📋 What Gets Created

After running build, you'll have:

```
frontend/
├── dist/                    ← Built React app (ready for deployment)
│   ├── index.html
│   ├── assets/
│   │   ├── js/
│   │   └── css/
│   └── ...
└── ...
```

The `customer-dashboard.php` file serves this `dist` folder when you visit the URL.

---

## 🌐 Access Points

| Page | URL |
|------|-----|
| Employee Dashboard | `http://localhost/NWCBilling/build/Employee.php` |
| Admin Dashboard | `http://localhost/NWCBilling/build/Admin.php` |
| **Customer Dashboard (NEW)** | `http://localhost/NWCBilling/customer-dashboard.php` |

---

## 🔧 Development vs Production

### During Development (with hot reload):
```bash
cd frontend
npm run dev
# Opens at http://localhost:5173/
```

### For Production (what we're doing):
```bash
cd frontend
npm run build
# Serves from http://localhost/NWCBilling/customer-dashboard.php
```

---

## ✅ Verification Checklist

After setup, verify:

- [ ] XAMPP is running (Apache & MySQL)
- [ ] Browser opens: `http://localhost/NWCBilling/customer-dashboard.php`
- [ ] Dashboard page loads with NWC Billing header
- [ ] Can search by account number (e.g., ACC-00001)
- [ ] Account information displays correctly
- [ ] Bills tab shows bill list

---

## ❌ Troubleshooting

### Error: "React app not built"
**Solution:** Run `npm run build` in the frontend folder

### Port 3000/5173 already in use
**Solution:** This doesn't affect the production URL, use build instead

### No styles/CSS loading
**Solution:** Make sure `npm run build` completed successfully and `dist` folder exists

### API calls failing
**Solution:** Check that XAMPP is running and PHP backend is accessible at `localhost/NWCBilling/build/Employee.php`

### Cannot find module
**Solution:** Run `npm install` in the frontend folder

---

## 📦 Next Steps

After customer dashboard is working:

1. Test account search functionality
2. Test bills display
3. Implement Meter Readings feature
4. Add more React components as needed

---

## 🎯 To Update the Dashboard

After making changes to React components:

1. **For development:**
   ```bash
   npm run dev
   # Test at http://localhost:5173/
   ```

2. **For production:**
   ```bash
   npm run build
   # Access at http://localhost/NWCBilling/customer-dashboard.php
   ```

Always run `npm run build` before committing changes!

---

**Questions?** Check the console for errors:
- Browser console (F12)
- PHP error logs at XAMPP/apache/logs/
