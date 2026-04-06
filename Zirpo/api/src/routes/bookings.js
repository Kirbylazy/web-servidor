import { Router } from 'express'
import { getBookings, createBooking, updateBookingStatus } from '../controllers/bookingsController.js'
import authenticate from '../middleware/auth.js'

const router = Router()

router.get('/', authenticate, getBookings)
router.post('/', authenticate, createBooking)
router.patch('/:id/status', authenticate, updateBookingStatus)

export default router
