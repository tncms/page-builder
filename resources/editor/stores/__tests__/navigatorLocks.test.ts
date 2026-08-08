import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useNavigatorLocksStore } from '../navigatorLocks'
import type { OwnerContext } from '../../types'

const page: OwnerContext = { type: 'page', id: 1, label: 'Page', title: 'Home' }
const post: OwnerContext = { type: 'post', id: 1, label: 'Post', title: 'Hello' }

describe('navigatorLocks store', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('locks, unlocks and toggles an id', () => {
    const locks = useNavigatorLocksStore()

    expect(locks.isLocked(page, 'a')).toBe(false)
    locks.lock(page, 'a')
    expect(locks.isLocked(page, 'a')).toBe(true)
    locks.unlock(page, 'a')
    expect(locks.isLocked(page, 'a')).toBe(false)
    locks.toggle(page, 'a')
    expect(locks.isLocked(page, 'a')).toBe(true)
  })

  it('does not duplicate a repeated lock', () => {
    const locks = useNavigatorLocksStore()
    locks.lock(page, 'a')
    locks.lock(page, 'a')
    expect([...locks.lockedSet(page)]).toEqual(['a'])
  })

  it('scopes locks per owner — one document never leaks into another', () => {
    const locks = useNavigatorLocksStore()
    locks.lock(page, 'a')
    expect(locks.isLocked(post, 'a')).toBe(false)
    expect([...locks.lockedSet(post)]).toEqual([])
  })

  it('exposes a ReadonlySet ready for describeNode', () => {
    const locks = useNavigatorLocksStore()
    locks.lock(page, 'a')
    const set = locks.lockedSet(page)
    expect(set.has('a')).toBe(true)
    expect(set.has('b')).toBe(false)
  })

  it('prunes ids no longer present in the tree', () => {
    const locks = useNavigatorLocksStore()
    locks.lock(page, 'a')
    locks.lock(page, 'b')
    locks.prune(page, ['a']) // 'b' no longer exists
    expect(locks.isLocked(page, 'a')).toBe(true)
    expect(locks.isLocked(page, 'b')).toBe(false)
  })

  it('prune is a no-op when nothing is stale', () => {
    const locks = useNavigatorLocksStore()
    locks.lock(page, 'a')
    locks.prune(page, new Set(['a', 'b']))
    expect([...locks.lockedSet(page)]).toEqual(['a'])
  })
})
