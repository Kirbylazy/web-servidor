import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import { AuthProvider } from './context/AuthContext'
import ProtectedRoute from './components/ProtectedRoute'
import Navbar from './components/Navbar'
import Login from './pages/Login'
import Register from './pages/Register'
import Home from './pages/Home'
import Profile from './pages/Profile'
import PublishTrip from './pages/PublishTrip'
import SearchTrips from './pages/SearchTrips'
import TripDetail from './pages/TripDetail'
import MyTrips from './pages/MyTrips'
import Messages from './pages/Messages'
import Chat from './pages/Chat'
import LiveTrip from './pages/LiveTrip'

const ProtectedWithNav = ({ children }) => (
  <ProtectedRoute>
    <Navbar />
    {children}
  </ProtectedRoute>
)

const App = () => {
  return (
    <BrowserRouter basename="/Zirpo">
      <AuthProvider>
        <Routes>
          <Route path="/login" element={<Login />} />
          <Route path="/register" element={<Register />} />
          <Route path="/" element={<ProtectedWithNav><Home /></ProtectedWithNav>} />
          <Route path="/profile" element={<ProtectedWithNav><Profile /></ProtectedWithNav>} />
          <Route path="/publish" element={<ProtectedWithNav><PublishTrip /></ProtectedWithNav>} />
          <Route path="/search" element={<ProtectedWithNav><SearchTrips /></ProtectedWithNav>} />
          <Route path="/trips/:id" element={<ProtectedWithNav><TripDetail /></ProtectedWithNav>} />
          <Route path="/my-trips" element={<ProtectedWithNav><MyTrips /></ProtectedWithNav>} />
          <Route path="/messages" element={<ProtectedWithNav><Messages /></ProtectedWithNav>} />
          <Route path="/chat/:tripId/:passengerId" element={<ProtectedWithNav><Chat /></ProtectedWithNav>} />
          <Route path="/live/:id" element={<ProtectedWithNav><LiveTrip /></ProtectedWithNav>} />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  )
}

export default App
