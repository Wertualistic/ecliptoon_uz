\Illuminate\Support\Facades\DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('user', 'admin', 'moderator', 'translator') DEFAULT 'user'");
echo "Done";
