import React from 'react';

interface Alert {
  id: number;
  type: string;
  severity: 'critical' | 'danger' | 'warning';
  message: string;
  acknowledged: boolean;
  student_name: string;
}

interface Props {
  alert: Alert;
}

export const AlertCard: React.FC<Props> = ({ alert }) => {
  const severityClass = `alert-${alert.severity}`;

  return (
    <div className={`alert-card ${severityClass}`}>
      <div className="alert-header">
        <h4>{alert.student_name}</h4>
        <span className="alert-severity">{alert.severity.toUpperCase()}</span>
      </div>
      <p className="alert-message">{alert.message}</p>
      <p className="alert-type">Type: {alert.type}</p>
      {!alert.acknowledged && <div className="alert-badge">Non traitée</div>}
    </div>
  );
};
