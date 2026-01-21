# Smart Parking System

A web-based parking management system I built to handle parking slot bookings, payments, and administration. Users can register, add their vehicles, book parking slots, and payments are automatically processed when they exit. Admins get a full dashboard to manage areas, slots, bookings, and view revenue.

## What It Does

- **User Features:**
  - Register and login
  - Add multiple vehicles (Bike, Car, Truck, SUV, etc.)
  - View available parking areas with real-time slot availability
  - Book parking slots instantly
  - Digital wallet system for payments
  - Automatic payment processing on exit (calculated by hours parked)
  - View booking history and active bookings

- **Admin Features:**
  - Dashboard with statistics (total areas, slots, bookings, revenue)
  - Manage parking areas (add/edit locations and hourly rates)
  - Manage parking slots (add/edit slots, mark as available/occupied/maintenance)
  - View all bookings and payment transactions
  - Monitor system activity

## Tech Stack

- **Backend:** PHP (procedural style)
- **Database:** MySQL
- **Frontend:** Bootstrap 5, Bootstrap Icons
- **Server:** Apache (XAMPP/WAMP)

## Installation

1. **Clone or download this repo** to your web server directory (htdocs/www)

2. **Set up the database:**
   - Open phpMyAdmin
   - Import `sql/database_complete.sql` - this creates the database and all tables with sample data
   - Or run the SQL file manually if you prefer

3. **Configure database connection:**
   - Edit `config/db.php`
   - Update these values to match your setup:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     define('DB_NAME', 'parking_system');
     ```

4. **Set up admin account:**
   - Run `setup_admin.php` in your browser to create/reset admin credentials
   - Default credentials (if using the SQL file): username `admin`, password `admin123`
   - **Important:** Change the default password after first login!

5. **Access the system:**
   - Open `http://localhost/Parking/` (adjust path if needed)
   - Register a new user account or login as admin

## Project Structure

```
Parking/
├── admin/              # Admin panel pages
│   ├── dashboard.php
│   ├── areas.php
│   ├── slots.php
│   ├── bookings.php
│   ├── payments.php
│   └── login.php
├── user/              # User pages
│   ├── dashboard.php
│   ├── register.php
│   ├── login.php
│   ├── vehicles.php
│   ├── book_slot.php
│   ├── bookings.php
│   ├── exit.php
│   └── wallet.php
├── config/
│   └── db.php         # Database configuration
├── includes/
│   ├── header.php     # Common header/navbar
│   └── footer.php     # Common footer
├── sql/
│   └── database_complete.sql  # Complete database setup
└── index.php          # Landing page
```

## How It Works

1. **User Registration:** Users sign up with name and email, password is hashed using PHP's `password_hash()`

2. **Vehicle Management:** Users add vehicles with vehicle number (unique globally) and type

3. **Booking Flow:**
   - User selects a parking area
   - Chooses an available slot
   - Selects their vehicle
   - Booking is created with entry time
   - Slot status changes to "Occupied"

4. **Payment:**
   - When user clicks "Exit", system calculates hours parked
   - Amount = hours × hourly_rate
   - Payment is deducted from user's wallet balance
   - If insufficient balance, payment fails (you can extend this to add top-up functionality)
   - Booking status changes to "Completed"
   - Slot becomes available again

5. **Wallet:** Each user has a wallet that can be topped up (you'll need to implement the top-up feature if needed)

## Database Schema

- `users` - User accounts
- `admin` - Admin accounts
- `vehicles` - User vehicles
- `parking_areas` - Parking locations with hourly rates
- `parking_slots` - Individual slots within areas
- `bookings` - Booking records
- `wallet` - User wallet balances
- `payments` - Payment transaction history


## Notes

- Make sure PHP sessions are enabled
- The system uses prepared statements to prevent SQL injection
- Passwords are hashed using `password_hash()` with bcrypt
- Timezone is set to Asia/Kolkata in `config/db.php` - change it to your timezone
- Base path is set to `/Parking/` in `includes/header.php` - adjust if your folder name is different

## Using This Project

### Getting Started

1. Fork or clone this repository
2. Follow the installation steps above
3. Customize the design, add your branding, or modify features as needed
4. Deploy to a web server when ready
