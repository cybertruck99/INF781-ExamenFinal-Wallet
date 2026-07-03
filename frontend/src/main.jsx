import React, { useCallback, useEffect, useRef, useState } from 'react'
import { createRoot } from 'react-dom/client'
import QRCode from 'qrcode'
import { api, tokenStore, money } from './api.js'
import './styles.css'

const RECAPTCHA_SITE_KEY = import.meta.env.VITE_RECAPTCHA_SITE_KEY || ''
const PASSWORD_MIN_LENGTH = 12
const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

const PASSWORD_RULES = [
  { key: 'length', label: `Mínimo ${PASSWORD_MIN_LENGTH} caracteres`, test: value => value.length >= PASSWORD_MIN_LENGTH },
  { key: 'lower', label: 'Una letra minúscula', test: value => /[a-z]/.test(value) },
  { key: 'upper', label: 'Una letra mayúscula', test: value => /[A-Z]/.test(value) },
  { key: 'number', label: 'Un número', test: value => /\d/.test(value) },
  { key: 'symbol', label: 'Un símbolo', test: value => /[^A-Za-z0-9]/.test(value) }
]

function ErrorBox({ error }) {
  if (!error) return null
  return <div className="alert error">{error}</div>
}

function SuccessBox({ message }) {
  if (!message) return null
  return <div className="alert ok">{message}</div>
}

function FieldError({ message }) {
  if (!message) return null
  return <span className="field-error">{message}</span>
}

function PasswordChecklist({ password }) {
  return <div className="password-rules">
    {PASSWORD_RULES.map(rule => {
      const ok = rule.test(password)
      return <span key={rule.key} className={ok ? 'ok' : ''}>{ok ? 'OK' : '-'} {rule.label}</span>
    })}
  </div>
}

function validateRegister(values) {
  const errors = {}
  const fullName = values.full_name.trim()
  const ci = values.ci.trim()
  const email = values.email.trim()
  const phone = values.phone.trim()
  const password = values.password

  if (!fullName) errors.full_name = 'Ingresa tu nombre completo.'
  else if (fullName.length < 3) errors.full_name = 'El nombre debe tener al menos 3 caracteres.'
  else if (!/^[\p{L}\p{M}\s'.-]+$/u.test(fullName)) errors.full_name = 'Usa solo letras, espacios y signos básicos.'

  if (!ci) errors.ci = 'Ingresa tu CI.'
  else if (ci.length < 4) errors.ci = 'El CI debe tener al menos 4 caracteres.'
  else if (!/^[0-9A-Za-z-]+$/.test(ci)) errors.ci = 'El CI solo acepta letras, números y guiones.'

  if (!email) errors.email = 'Ingresa tu correo.'
  else if (!email.includes('@')) errors.email = 'El correo debe incluir @.'
  else if (!EMAIL_RE.test(email)) errors.email = 'Ingresa un correo válido, por ejemplo nombre@dominio.com.'

  if (!phone) errors.phone = 'Ingresa tu teléfono.'
  else if (phone.length < 7) errors.phone = 'El teléfono debe tener al menos 7 caracteres.'
  else if (!/^[0-9+ -]+$/.test(phone)) errors.phone = 'El teléfono solo acepta números, espacios, + y guiones.'

  if (!password) errors.password = 'Crea una contraseña.'
  else if (!PASSWORD_RULES.every(rule => rule.test(password))) {
    errors.password = 'La contraseña debe cumplir todos los requisitos de seguridad.'
  }

  return errors
}

function firstApiErrors(errors = {}) {
  return Object.fromEntries(Object.entries(errors).map(([field, messages]) => [
    field,
    Array.isArray(messages) ? messages[0] : String(messages)
  ]))
}

function CaptchaWidget({ siteKey, reloadKey, onVerify, onExpire, onError, onStatus }) {
  const containerRef = useRef(null)
  const widgetRef = useRef(null)

  useEffect(() => {
    if (!siteKey) {
      onStatus('Obteniendo configuración de CAPTCHA...')
      return undefined
    }

    let cancelled = false
    let settled = false
    let intervalId = null
    let timeoutId = null
    const readyCallbackName = '__secureWalletCaptchaReady'

    function renderWidget() {
      if (cancelled || !containerRef.current || widgetRef.current !== null || !window.grecaptcha) return

      settled = true
      onStatus('Marca la casilla No soy un robot.')
      widgetRef.current = window.grecaptcha.render(containerRef.current, {
        sitekey: siteKey,
        theme: 'light',
        callback: token => onVerify(token),
        'expired-callback': () => {
          onVerify('')
          onExpire()
        },
        'error-callback': () => {
          onVerify('')
          onError('No se pudo completar reCAPTCHA. Intenta nuevamente.')
        }
      })
    }

    onVerify('')
    onStatus('Cargando CAPTCHA...')
    window[readyCallbackName] = renderWidget
    const sources = [
      `https://www.google.com/recaptcha/api.js?onload=${readyCallbackName}&render=explicit&hl=es`,
      `https://www.recaptcha.net/recaptcha/api.js?onload=${readyCallbackName}&render=explicit&hl=es`
    ]
    const staleScript = document.querySelector('script[data-captcha-script="true"]')
    if (staleScript && !window.grecaptcha) staleScript.remove()
    let script = null
    let sourceIndex = 0

    function loadScript() {
      script = document.createElement('script')
      script.dataset.captchaScript = 'true'
      script.src = sources[sourceIndex]
      script.async = true
      script.defer = true
      script.addEventListener('load', () => window.setTimeout(renderWidget, 0))
      script.addEventListener('error', handleScriptError)
      document.head.appendChild(script)
    }

    const handleScriptError = () => {
      if (cancelled) return
      script?.remove()
      if (sourceIndex < sources.length - 1) {
        sourceIndex += 1
        onStatus('Reintentando CAPTCHA...')
        loadScript()
        return
      }
      onError('No se pudo cargar Google reCAPTCHA. Revisa la conexión e intenta nuevamente.')
    }

    if (window.grecaptcha) {
      renderWidget()
    } else {
      loadScript()
    }

    intervalId = window.setInterval(renderWidget, 150)
    timeoutId = window.setTimeout(() => {
      if (!cancelled && !settled) {
        onError('reCAPTCHA tardó demasiado en cargar. Presiona Reintentar.')
      }
    }, 10000)

    return () => {
      cancelled = true
      window.clearInterval(intervalId)
      window.clearTimeout(timeoutId)
      script?.removeEventListener('error', handleScriptError)
      if (widgetRef.current !== null && window.grecaptcha) {
        window.grecaptcha.reset(widgetRef.current)
      }
      if (containerRef.current) {
        containerRef.current.innerHTML = ''
      }
      if (window[readyCallbackName] === renderWidget) {
        delete window[readyCallbackName]
      }
      widgetRef.current = null
    }
  }, [siteKey, reloadKey, onError, onExpire, onStatus, onVerify])

  return <div className="captcha-box" ref={containerRef} />
}

function AuthScreen({ onAuth }) {
  const [mode, setMode] = useState('login')
  const [login, setLogin] = useState({ email: '', password: '', captcha_token: '' })
  const [register, setRegister] = useState({ full_name: '', ci: '', email: '', phone: '', password: '', captcha_token: '' })
  const [registerTouched, setRegisterTouched] = useState({})
  const [apiLoginErrors, setApiLoginErrors] = useState({})
  const [apiRegisterErrors, setApiRegisterErrors] = useState({})
  const [captchaSiteKey, setCaptchaSiteKey] = useState('')
  const [captchaStatus, setCaptchaStatus] = useState('CAPTCHA pendiente')
  const [mfaTicket, setMfaTicket] = useState(null)
  const [mfaCode, setMfaCode] = useState('')
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)
  const [captchaKey, setCaptchaKey] = useState(0)

  const clientRegisterErrors = validateRegister(register)
  const registerErrors = { ...clientRegisterErrors, ...apiRegisterErrors }

  function updateLogin(field, value) {
    setLogin(prev => ({ ...prev, [field]: value }))
    setApiLoginErrors(prev => {
      const next = { ...prev }
      delete next[field]
      return next
    })
  }

  function updateRegister(field, value) {
    setRegister(prev => ({ ...prev, [field]: value }))
    setRegisterTouched(prev => ({ ...prev, [field]: true }))
    setApiRegisterErrors(prev => {
      const next = { ...prev }
      delete next[field]
      return next
    })
  }

  function touchRegister(field) {
    setRegisterTouched(prev => ({ ...prev, [field]: true }))
  }

  function resetCaptcha(message = 'CAPTCHA pendiente') {
    setLogin(prev => ({ ...prev, captcha_token: '' }))
    setRegister(prev => ({ ...prev, captcha_token: '' }))
    setCaptchaStatus(message)
    setCaptchaKey(value => value + 1)
  }

  function switchMode(nextMode) {
    setMode(nextMode)
    setError('')
    setApiLoginErrors({})
    setApiRegisterErrors({})
    resetCaptcha()
  }

  useEffect(() => {
    if (captchaSiteKey) return

    let active = true
    setCaptchaStatus('Obteniendo configuración de CAPTCHA...')
    api('/auth/captcha/site-key')
      .then(data => {
        if (!active) return
        setCaptchaSiteKey(data.site_key)
      })
      .catch(err => {
        if (!active) return
        if (RECAPTCHA_SITE_KEY) {
          setCaptchaSiteKey(RECAPTCHA_SITE_KEY)
          setCaptchaStatus('Cargando CAPTCHA...')
          return
        }
        setCaptchaStatus('CAPTCHA no disponible')
        setError(err.message)
      })

    return () => { active = false }
  }, [captchaSiteKey])

  const handleCaptchaVerify = useCallback(token => {
    if (mode === 'login') {
      setLogin(prev => ({ ...prev, captcha_token: token }))
    } else {
      setRegister(prev => ({ ...prev, captcha_token: token }))
    }
    setApiLoginErrors(prev => {
      const next = { ...prev }
      delete next.captcha_token
      return next
    })
    setApiRegisterErrors(prev => {
      const next = { ...prev }
      delete next.captcha_token
      return next
    })
    setCaptchaStatus(token ? 'CAPTCHA completado' : 'CAPTCHA pendiente')
  }, [mode])

  const handleCaptchaExpire = useCallback(() => {
    setCaptchaStatus('El CAPTCHA expiró')
  }, [])

  const handleCaptchaError = useCallback(message => {
    setCaptchaStatus('CAPTCHA no disponible')
    setError(message)
  }, [])

  async function doLogin(e) {
    e.preventDefault()
    setError('')
    setApiLoginErrors({})

    if (!login.captcha_token) {
      setError('Marca la casilla No soy un robot antes de ingresar.')
      setCaptchaStatus('CAPTCHA requerido')
      return
    }

    setLoading(true)
    try {
      const data = await api('/auth/login', { method: 'POST', body: JSON.stringify(login) })
      if (data.mfa_required) {
        setMfaTicket(data.ticket)
      } else {
        tokenStore.save(data.tokens)
        onAuth(data.user)
      }
    } catch (err) {
      setApiLoginErrors(firstApiErrors(err.payload?.errors))
      resetCaptcha('CAPTCHA pendiente')
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }

  async function verifyMfa(e) {
    e.preventDefault()
    setError('')
    setLoading(true)
    try {
      const data = await api('/auth/mfa/verify', { method: 'POST', body: JSON.stringify({ ticket: mfaTicket, code: mfaCode }) })
      tokenStore.save(data.tokens)
      onAuth(data.user)
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }

  async function doRegister(e) {
    e.preventDefault()
    setError('')
    setApiRegisterErrors({})
    setRegisterTouched({
      full_name: true,
      ci: true,
      email: true,
      phone: true,
      password: true
    })

    const errors = validateRegister(register)
    if (Object.keys(errors).length > 0) {
      setError('Corrige los campos marcados antes de crear la cuenta.')
      return
    }

    if (!register.captcha_token) {
      setError('Marca la casilla No soy un robot antes de registrarte.')
      setCaptchaStatus('CAPTCHA requerido')
      return
    }

    setLoading(true)
    try {
      await api('/auth/register', { method: 'POST', body: JSON.stringify(register) })
      setLogin({ email: '', password: '', captcha_token: '' })
      switchMode('login')
    } catch (err) {
      setApiRegisterErrors(firstApiErrors(err.payload?.errors))
      resetCaptcha('CAPTCHA pendiente')
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }

  if (mfaTicket) {
    return <main className="auth-card">
      <h1>SecureWallet</h1>
      <p>La cuenta tiene MFA activo. Ingresa el código de 6 dígitos de Google Authenticator.</p>
      <ErrorBox error={error} />
      <form onSubmit={verifyMfa} className="form-grid">
        <label>Código TOTP<input value={mfaCode} onChange={e => setMfaCode(e.target.value)} maxLength="6" required /></label>
        <button disabled={loading}>Verificar MFA</button>
      </form>
    </main>
  }

  return <main className="auth-card">
    <h1>SecureWallet</h1>
    <div className="tabs">
      <button className={mode === 'login' ? 'active' : ''} onClick={() => switchMode('login')}>Login</button>
      <button className={mode === 'register' ? 'active' : ''} onClick={() => switchMode('register')}>Registro</button>
    </div>
    <ErrorBox error={error} />
    {mode === 'login' ? <form onSubmit={doLogin} className="form-grid">
      <label>Correo
        <input type="email" value={login.email} onChange={e => updateLogin('email', e.target.value)} required />
        <FieldError message={apiLoginErrors.email} />
      </label>
      <label>Contraseña
        <input type="password" value={login.password} onChange={e => updateLogin('password', e.target.value)} required />
        <FieldError message={apiLoginErrors.password} />
      </label>
      <div className="captcha-panel">
        <span>{captchaStatus}</span>
        <CaptchaWidget
          key={captchaKey}
          siteKey={captchaSiteKey}
          reloadKey={captchaKey}
          onVerify={handleCaptchaVerify}
          onExpire={handleCaptchaExpire}
          onError={handleCaptchaError}
          onStatus={setCaptchaStatus}
        />
        <FieldError message={apiLoginErrors.captcha_token} />
        <button type="button" className="secondary" onClick={() => resetCaptcha()}>Reintentar CAPTCHA</button>
      </div>
      <button disabled={loading || !login.captcha_token}>Ingresar</button>
    </form> : <form onSubmit={doRegister} className="form-grid" noValidate>
      <label>Nombre completo
        <input
          value={register.full_name}
          onBlur={() => touchRegister('full_name')}
          onChange={e => updateRegister('full_name', e.target.value)}
          className={registerTouched.full_name && registerErrors.full_name ? 'invalid' : ''}
          aria-invalid={Boolean(registerTouched.full_name && registerErrors.full_name)}
          required
        />
        <FieldError message={registerTouched.full_name && registerErrors.full_name} />
      </label>
      <label>CI
        <input
          value={register.ci}
          onBlur={() => touchRegister('ci')}
          onChange={e => updateRegister('ci', e.target.value)}
          className={registerTouched.ci && registerErrors.ci ? 'invalid' : ''}
          aria-invalid={Boolean(registerTouched.ci && registerErrors.ci)}
          required
        />
        <FieldError message={registerTouched.ci && registerErrors.ci} />
      </label>
      <label>Correo
        <input
          type="email"
          value={register.email}
          onBlur={() => touchRegister('email')}
          onChange={e => updateRegister('email', e.target.value)}
          className={registerTouched.email && registerErrors.email ? 'invalid' : ''}
          aria-invalid={Boolean(registerTouched.email && registerErrors.email)}
          required
        />
        <FieldError message={registerTouched.email && registerErrors.email} />
      </label>
      <label>Teléfono
        <input
          value={register.phone}
          onBlur={() => touchRegister('phone')}
          onChange={e => updateRegister('phone', e.target.value)}
          className={registerTouched.phone && registerErrors.phone ? 'invalid' : ''}
          aria-invalid={Boolean(registerTouched.phone && registerErrors.phone)}
          required
        />
        <FieldError message={registerTouched.phone && registerErrors.phone} />
      </label>
      <label>Contraseña
        <input
          type="password"
          minLength={PASSWORD_MIN_LENGTH}
          value={register.password}
          onBlur={() => touchRegister('password')}
          onChange={e => updateRegister('password', e.target.value)}
          className={registerTouched.password && registerErrors.password ? 'invalid' : ''}
          aria-invalid={Boolean(registerTouched.password && registerErrors.password)}
          required
        />
        <PasswordChecklist password={register.password} />
        <FieldError message={registerTouched.password && registerErrors.password} />
      </label>
      <div className="captcha-panel">
        <span>{captchaStatus}</span>
        <CaptchaWidget
          key={captchaKey}
          siteKey={captchaSiteKey}
          reloadKey={captchaKey}
          onVerify={handleCaptchaVerify}
          onExpire={handleCaptchaExpire}
          onError={handleCaptchaError}
          onStatus={setCaptchaStatus}
        />
        <FieldError message={apiRegisterErrors.captcha_token} />
        <button type="button" className="secondary" onClick={() => resetCaptcha()}>Reintentar CAPTCHA</button>
      </div>
      <button disabled={loading || !register.captcha_token}>Crear cuenta</button>
    </form>}
  </main>
}

function Dashboard({ user, onLogout }) {
  const [currentUser, setCurrentUser] = useState(user)
  const [wallet, setWallet] = useState(null)
  const [transactions, setTransactions] = useState([])
  const [adminUsers, setAdminUsers] = useState([])
  const [auditLogs, setAuditLogs] = useState([])
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  const [topup, setTopup] = useState({ monto: '', descripcion: '' })
  const [transfer, setTransfer] = useState({ destinatario: '70000003', monto: '', descripcion: '' })
  const [pendingTransfer, setPendingTransfer] = useState(null)
  const [totpCode, setTotpCode] = useState('')
  const [qrDataUrl, setQrDataUrl] = useState('')

  const isAdmin = currentUser?.role === 'ADMIN'

  async function loadAll() {
    setError('')
    try {
      const [me, walletData, txData] = await Promise.all([
        api('/me'),
        api('/wallet'),
        api('/transactions?per_page=10')
      ])
      setCurrentUser(me.user)
      setWallet(walletData.wallet)
      setTransactions(txData.data)
      if (me.user.role === 'ADMIN') await loadAdmin()
    } catch (err) {
      setError(err.message)
    }
  }

  async function loadAdmin() {
    const [users, logs] = await Promise.all([
      api('/admin/users?per_page=20'),
      api('/admin/audit-logs?per_page=20')
    ])
    setAdminUsers(users.data)
    setAuditLogs(logs.data)
  }

  useEffect(() => { loadAll() }, [])

  async function doTopup(e) {
    e.preventDefault()
    setError('')
    setMessage('')
    try {
      const data = await api('/wallet/topup', { method: 'POST', body: JSON.stringify(topup) })
      setWallet(data.wallet)
      setMessage(data.message)
      setTopup({ monto: '', descripcion: '' })
      await loadAll()
    } catch (err) {
      setError(err.message)
    }
  }

  async function createTransfer(e) {
    e.preventDefault()
    setError('')
    setMessage('')
    try {
      const data = await api('/transfers', {
        method: 'POST',
        headers: { 'Idempotency-Key': crypto.randomUUID() },
        body: JSON.stringify(transfer)
      })
      setPendingTransfer(data)
      setMessage('Transferencia pendiente. Confirma para ejecutarla.')
    } catch (err) {
      setError(err.message)
    }
  }

  async function confirmTransfer(e) {
    e.preventDefault()
    setError('')
    setMessage('')
    try {
      const data = await api(`/transfers/${pendingTransfer.uuid}/confirm`, {
        method: 'POST',
        body: JSON.stringify({ confirmed: true, totp_code: totpCode || undefined })
      })
      setWallet(data.wallet)
      setPendingTransfer(null)
      setTotpCode('')
      setTransfer({ destinatario: '70000003', monto: '', descripcion: '' })
      setMessage(data.message)
      await loadAll()
    } catch (err) {
      setError(err.message)
    }
  }

  async function enableMfa() {
    setError('')
    setMessage('')
    try {
      const data = await api('/auth/mfa/enable', { method: 'POST', body: JSON.stringify({}) })
      const image = await QRCode.toDataURL(data.otpauth_uri, { errorCorrectionLevel: 'M' })
      setQrDataUrl(image)
      setMessage('Escanea el QR y confirma el código con el formulario inferior.')
    } catch (err) {
      setError(err.message)
    }
  }

  async function confirmMfa(e) {
    e.preventDefault()
    setError('')
    setMessage('')
    try {
      await api('/auth/mfa/enable/confirm', { method: 'POST', body: JSON.stringify({ code: totpCode }) })
      setQrDataUrl('')
      setTotpCode('')
      setMessage('MFA activado correctamente.')
      await loadAll()
    } catch (err) {
      setError(err.message)
    }
  }

  async function toggleBlock(target) {
    setError('')
    try {
      await api(`/admin/users/${target.uuid}/block`, {
        method: 'PATCH',
        body: JSON.stringify({ blocked: !target.is_blocked })
      })
      await loadAdmin()
    } catch (err) {
      setError(err.message)
    }
  }

  async function logout() {
    try {
      await api('/auth/logout', { method: 'POST', body: JSON.stringify({ refresh_token: tokenStore.refresh }) })
    } catch (_) {}
    tokenStore.clear()
    onLogout()
  }

  return <div className="layout">
    <header className="topbar">
      <div>
        <strong>SecureWallet</strong>
        <span>{currentUser?.full_name} · {currentUser?.role}</span>
      </div>
      <button onClick={logout}>Cerrar sesión</button>
    </header>

    <ErrorBox error={error} />
    <SuccessBox message={message} />

    <section className="grid two">
      <article className="card">
        <h2>Perfil</h2>
        <p><b>Correo:</b> {currentUser?.email}</p>
        <p><b>Teléfono:</b> {currentUser?.phone}</p>
        <p><b>MFA:</b> {currentUser?.mfa_enabled ? 'Activo' : 'Inactivo'}</p>
        {!currentUser?.mfa_enabled && <button onClick={enableMfa}>Activar MFA</button>}
        {qrDataUrl && <form onSubmit={confirmMfa} className="form-grid compact">
          <img className="qr" src={qrDataUrl} alt="QR MFA" />
          <label>Código TOTP<input value={totpCode} onChange={e => setTotpCode(e.target.value)} maxLength="6" /></label>
          <button>Confirmar MFA</button>
        </form>}
      </article>

      <article className="card balance-card">
        <h2>Saldo</h2>
        <p className="balance">Bs {money(wallet?.balance)}</p>
        <button onClick={loadAll}>Actualizar</button>
      </article>
    </section>

    <section className="grid two">
      <article className="card">
        <h2>Recarga</h2>
        <form onSubmit={doTopup} className="form-grid compact">
          <label>Monto Bs<input type="number" step="0.01" min="1" max="5000" value={topup.monto} onChange={e => setTopup({ ...topup, monto: e.target.value })} required /></label>
          <label>Descripción<input value={topup.descripcion} onChange={e => setTopup({ ...topup, descripcion: e.target.value })} /></label>
          <button>Recargar</button>
        </form>
      </article>

      <article className="card">
        <h2>Transferencia</h2>
        <form onSubmit={createTransfer} className="form-grid compact">
          <label>Correo o teléfono destino<input value={transfer.destinatario} onChange={e => setTransfer({ ...transfer, destinatario: e.target.value })} required /></label>
          <label>Monto Bs<input type="number" step="0.01" min="1" max="5000" value={transfer.monto} onChange={e => setTransfer({ ...transfer, monto: e.target.value })} required /></label>
          <label>Descripción<input value={transfer.descripcion} onChange={e => setTransfer({ ...transfer, descripcion: e.target.value })} /></label>
          <button>Crear transferencia</button>
        </form>
        {pendingTransfer && <form onSubmit={confirmTransfer} className="confirm-box">
          <h3>Confirmación</h3>
          <p>Destinatario: <b>{pendingTransfer.destinatario.full_name}</b></p>
          <p>Monto: <b>Bs {pendingTransfer.monto}</b></p>
          {pendingTransfer.requiere_totp && <label>Código TOTP<input value={totpCode} onChange={e => setTotpCode(e.target.value)} maxLength="6" required /></label>}
          <button>Confirmar y ejecutar</button>
        </form>}
      </article>
    </section>

    <section className="card">
      <h2>Historial propio</h2>
      <table>
        <thead><tr><th>Fecha</th><th>Tipo</th><th>Monto</th><th>Contraparte</th><th>Saldo resultante</th></tr></thead>
        <tbody>{transactions.map(tx => <tr key={tx.uuid}>
          <td>{new Date(tx.created_at).toLocaleString()}</td>
          <td>{tx.type}</td>
          <td>Bs {tx.amount}</td>
          <td>{tx.counterparty?.full_name || '-'}</td>
          <td>Bs {tx.balance_after}</td>
        </tr>)}</tbody>
      </table>
    </section>

    {isAdmin && <section className="grid two">
      <article className="card">
        <h2>Admin · Usuarios</h2>
        <table>
          <thead><tr><th>Nombre</th><th>Rol</th><th>Bloqueo</th><th></th></tr></thead>
          <tbody>{adminUsers.map(u => <tr key={u.uuid}>
            <td>{u.full_name}<br /><small>{u.email}</small></td>
            <td>{u.role}</td>
            <td>{u.is_blocked ? 'Bloqueado' : 'Activo'}</td>
            <td><button onClick={() => toggleBlock(u)}>{u.is_blocked ? 'Desbloquear' : 'Bloquear'}</button></td>
          </tr>)}</tbody>
        </table>
      </article>
      <article className="card">
        <h2>Admin · Auditoría</h2>
        <table>
          <thead><tr><th>Fecha</th><th>Acción</th><th>IP</th></tr></thead>
          <tbody>{auditLogs.map(log => <tr key={log.uuid}>
            <td>{new Date(log.created_at).toLocaleString()}</td>
            <td>{log.action}</td>
            <td>{log.ip || '-'}</td>
          </tr>)}</tbody>
        </table>
      </article>
    </section>}
  </div>
}

function App() {
  const [user, setUser] = useState(null)
  const [checked, setChecked] = useState(false)

  useEffect(() => {
    async function init() {
      if (tokenStore.access) {
        try {
          const data = await api('/me')
          setUser(data.user)
        } catch (_) {
          tokenStore.clear()
        }
      }
      setChecked(true)
    }
    init()
  }, [])

  if (!checked) return <main className="auth-card"><h1>SecureWallet</h1><p>Cargando...</p></main>
  return user ? <Dashboard user={user} onLogout={() => setUser(null)} /> : <AuthScreen onAuth={setUser} />
}

createRoot(document.getElementById('root')).render(<App />)
