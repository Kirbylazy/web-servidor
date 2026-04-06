import { Router } from 'express'
import { getUserById, updateUser } from '../controllers/usersController.js'

const router = Router()

router.get('/:id', getUserById)
router.put('/:id', updateUser)

export default router
