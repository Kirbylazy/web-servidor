import { Router } from 'express'
import { getUserById, updateUser, updatePassword, updatePhoto } from '../controllers/usersController.js'
import { getVehicle, saveVehicle } from '../controllers/vehiclesController.js'
import authenticate from '../middleware/auth.js'
import { uploadPhoto } from '../middleware/upload.js'

const router = Router()

router.get('/:id', authenticate, getUserById)
router.put('/:id', authenticate, updateUser)
router.put('/:id/password', authenticate, updatePassword)
router.post('/:id/photo', authenticate, uploadPhoto.single('foto'), updatePhoto)
router.get('/:id/vehicle', authenticate, getVehicle)
router.post('/:id/vehicle', authenticate, saveVehicle)

export default router
