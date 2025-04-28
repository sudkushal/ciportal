namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Check if the user is logged in
        if (!session()->get('isLoggedIn')) {
            // If not logged in, redirect to login page
            return redirect()->to('/login');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // You can add logic for actions after the request is processed, if needed.
    }
}
