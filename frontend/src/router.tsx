import { createBrowserRouter } from "react-router";
import LoginForm from "./features/auth/LoginForm/LoginForm";
import RegisterForm from "./features/auth/RegisterForm/RegisterForm";

export const router = createBrowserRouter([
  { path: "/login", element: <LoginForm /> },
  { path: "/register", element: <RegisterForm /> },
]);
