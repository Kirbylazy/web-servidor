import { Router } from 'express'
import { getTrips, getTripById, createTrip, updateTrip, deleteTrip } from '../controllers/tripsController.js'

const router = Router()

router.get('/', getTrips)
router.get('/:id', getTripById)
router.post('/', createTrip)
router.put('/:id', updateTrip)
router.delete('/:id', deleteTrip)

export default router
