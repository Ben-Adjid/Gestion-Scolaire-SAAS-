import React from 'react';

interface Stats {
  total_students: number;
  total_classes: number;
  absences_today: number;
  critical_alerts: number;
}

interface Props {
  stats: Stats;
}

export const StatsGrid: React.FC<Props> = ({ stats }) => {
  return (
    <div className="stats-grid">
      <div className="stat-card">
        <h3>Étudiants</h3>
        <p className="stat-value">{stats.total_students}</p>
      </div>
      <div className="stat-card">
        <h3>Classes</h3>
        <p className="stat-value">{stats.total_classes}</p>
      </div>
      <div className="stat-card">
        <h3>Absences Aujourd'hui</h3>
        <p className="stat-value">{stats.absences_today}</p>
      </div>
      <div className="stat-card alert">
        <h3>Alertes Critiques</h3>
        <p className="stat-value">{stats.critical_alerts}</p>
      </div>
    </div>
  );
};
