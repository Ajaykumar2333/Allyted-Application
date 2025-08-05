const adminBtn = document.getElementById('adminBtn');
const employeeBtn = document.getElementById('employeeBtn');
const loginForm = document.getElementById('loginForm');
const userTypeInput = document.getElementById('userType');
const forgotLink = document.getElementById('forgotPasswordLink');

// Set default: Admin
let currentUserType = 'admin';

adminBtn.onclick = () => {
  currentUserType = 'admin';
  userTypeInput.value = 'admin';
  loginForm.action = 'admin_login.php';
  adminBtn.classList.add('active');
  employeeBtn.classList.remove('active');
};

employeeBtn.onclick = () => {
  currentUserType = 'employee';
  userTypeInput.value = 'employee';
  loginForm.action = 'employee_login.php';
  adminBtn.classList.remove('active');
  employeeBtn.classList.add('active');
};

// Handle forgot password based on selected user
forgotLink.onclick = (e) => {
  e.preventDefault();
  if (currentUserType === 'admin') {
    window.location.href = 'forgot_password.php?type=admin';
  } else {
    window.location.href = 'forgot_password.php?type=employee';
  }
};
