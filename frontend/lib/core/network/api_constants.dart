class ApiConstants {
  static const String baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://192.168.1.8:8000/api',
  );

  static const String login = "/login";
  static const String employeeLogin = "/employee/login";
  static const String registerPharmacist = "/register";
  static const String registerEmployee = "/employee/register";
  static const String registrationStatus = "/registration/status";
  static const String me = "/me";
  static const String logout = "/logout";
  static const String profileUpdate = "/profile/update";
  static const String passwordChange = "/password/change";
  static const String accountDeactivate = "/account/deactivate";
  static const String pharmacyProfileUpdate = "/pharmacy/profile/update";
  static const String pharmacyAdd = "/pharmacy/add";
  static const String supportTickets = "/support/tickets";

  static const String medicines = "/medicines";
  static const String medicineAdd = "/medicines/add";
  static const String medicineEdit = "/medicines/edit";

  static const String suppliers = "/suppliers";
  static const String orders = "/orders";

  static const String saleCreate = "/sale/create";
  static const String saleAll = "/sale/all";
  static const String saleDaily = "/sale/daily";

  static const String reportsDashboard = "/reports/dashboard";
  static const String reportsRevenue = "/reports/revenue";
  static const String reportsProfits = "/reports/profits";
  static const String reportsInventoryValue = "/reports/inventory-value";
  static const String reportsMostSold = "/reports/most-sold";

  static const String employees = "/employees";
  static const String employeesPending = "/employees/pending";

  static const String tasks = "/tasks";
  static const String tasksPharmacy = "/tasks/pharmacy";

  static const String notifications = "/notifications";
  static const String notificationsReadAll = "/notifications/read-all";
  static const String saleMySales = "/sale/my-sales";

  static const String employeeOffers = "/employee/offers";
  static const String recruitmentOffers = "/recruitment/offers";
  static const String employeeNotifications = "/employee/notifications";
  static const String employeeProfile = "/employee/profile";
  static const String employeeProfileUpdate = "/employee/profile/update";
  static const String employeePasswordChange = "/employee/password/change";

  static const String rating = "/rating";
}
