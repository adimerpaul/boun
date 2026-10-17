export function companyData () {
  try {
    return JSON.parse(localStorage.getItem('empresaBuonPrezzo') || '{}')
  } catch {
    return {}
  }
}
