import { useEffect, useState } from 'react'
import client from '../api/client'

// Grille de cases à cocher regroupées par module, pour accorder des droits précis à un compte.
export default function PermissionsGrille({ value = [], onChange }) {
  const [modules, setModules] = useState([])

  useEffect(() => {
    client.get('/permissions').then((res) => setModules(res.data)).catch(() => setModules([]))
  }, [])

  if (modules.length === 0) return null

  const coche = (code) => value.includes(code)
  const basculer = (code) => onChange(coche(code) ? value.filter((c) => c !== code) : [...value, code])

  return (
    <section className="permissions-grille">
      <h3>Droits d’accès supplémentaires</h3>
      <p className="form-section-aide">Accordez des droits précis en plus du rôle. Les administrateurs ont déjà tous les droits.</p>
      <div className="permissions-modules">
        {modules.map((m) => (
          <fieldset key={m.module} className="permissions-module">
            <legend>{m.module}</legend>
            {m.permissions.map((p) => (
              <label key={p.code} className={`permission-option ${coche(p.code) ? 'permission-option--active' : ''}`}>
                <input type="checkbox" checked={coche(p.code)} onChange={() => basculer(p.code)} />
                <span>{p.libelle}</span>
              </label>
            ))}
          </fieldset>
        ))}
      </div>
    </section>
  )
}
