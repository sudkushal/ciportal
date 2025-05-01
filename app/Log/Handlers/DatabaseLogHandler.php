<?php

namespace App\Log\Handlers; // Adjust namespace as needed

use CodeIgniter\Log\Handlers\BaseHandler;
use CodeIgniter\Log\Handlers\HandlerInterface;
use Config\Database; // To get DB connection

class DatabaseLogHandler extends BaseHandler implements HandlerInterface
{
    protected $db;
    protected $logTable = 'ci_logs'; // Name of your log table

    public function __construct(array $config = [])
    {
        parent::__construct($config);

        // Get database connection
        // Ensure your default DB group is configured correctly
        try {
            $this->db = Database::connect();
        } catch (\Throwable $e) {
            // Cannot log to DB if DB connection fails! Fallback or error needed.
            // Maybe log to file here as a last resort?
            log_message('critical', 'DatabaseLogHandler failed to connect to DB: ' . $e->getMessage());
            $this->db = null; // Ensure db is null if connection failed
        }
    }

    /**
     * Handles logging the message.
     * If the handler returns false, the log event stops processing.
     */
    public function handle($level, $message): bool
    {
        if (!$this->db) {
            return false; // Cannot log if DB connection failed
        }

        // Check if the level is handled by this handler
        if ($this->handles($level)) {
            // Prepare data for insertion
            $logData = [
                'level'      => $level,
                // Interpolate context if message is like 'User {id} logged in'
                // $message will already have context interpolated by the Logger
                'message'    => $message,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'cli', // Get IP or note if CLI
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'cli',
                'log_time'   => date('Y-m-d H:i:s'), // Or use database NOW() function
            ];

            try {
                // Insert into the database
                $builder = $this->db->table($this->logTable);
                $builder->insert($logData);
            } catch (\Throwable $e) {
                // Log insertion error to file as fallback?
                log_message('critical', 'DatabaseLogHandler failed to insert log: ' . $e->getMessage());
                // Decide if failure here should stop further log processing
                return false;
            }
        }

        return true; // Return true to allow other handlers to process if needed
    }
}
