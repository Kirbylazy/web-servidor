import { Router } from 'express'
import { getTrips, getTripById, createTrip, updateTrip, deleteTrip, getMyTrips } from '../controllers/tripsController.js'
import authenticate from '../middleware/auth.js'

const router = Router()

router.get('/', authenticate, getTrips)
router.get('/my', authenticate, getMyTrips)
router.get('/:id', authenticate, getTripById)
router.post('/', authenticate, createTrip)
router.put('/:id', authenticate, updateTrip)
router.delete('/:id', authenticate, deleteTrip)

export default router
